import fs from 'node:fs/promises';
import { existsSync, readFileSync } from 'node:fs';
import net from 'node:net';
import path from 'node:path';
import { spawn } from 'node:child_process';
import readline from 'node:readline/promises';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import mysql from 'mysql2/promise';
import { unserialize } from 'php-serialize';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const repoRoot = path.resolve(__dirname, '..');
const authPath = path.join(repoRoot, '.codex', 'local-auth.json');
const cdpUrl = 'http://127.0.0.1:9222';
const defaultDbPort = 10005;
const interactive = process.stdin.isTTY && process.stdout.isTTY;
const wpConfigPath = path.resolve(repoRoot, '..', '..', '..', 'wp-config.php');

function chromeCandidates() {
  if (process.env.CHROME_PATH) {
    return [process.env.CHROME_PATH];
  }

  if (process.platform === 'win32') {
    return [
      'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
      'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
      'C:\\Program Files\\Chromium\\Application\\chrome.exe',
      'C:\\Program Files (x86)\\Chromium\\Application\\chrome.exe',
    ];
  }

  return [
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/opt/google/chrome/chrome',
  ];
}

async function readAuthConfig() {
  try {
    const raw = await fs.readFile(authPath, 'utf8');
    return JSON.parse(raw);
  } catch {
    return {};
  }
}

async function writeAuthConfig(config) {
  await fs.mkdir(path.dirname(authPath), { recursive: true });
  await fs.writeFile(authPath, `${JSON.stringify(config, null, 2)}\n`, 'utf8');
}

function parseWpConfig() {
  if (!existsSync(wpConfigPath)) {
    return null;
  }

  const raw = readFileSync(wpConfigPath, 'utf8');
  const getConstant = (name) => {
    const match = raw.match(new RegExp(`define\\(\\s*['"]${name}['"]\\s*,\\s*['"]([^'"]*)['"]\\s*\\)`));
    return match ? match[1] : '';
  };
  const prefixMatch = raw.match(/\$table_prefix\s*=\s*['"]([^'"]+)['"]/);

  return {
    dbName: getConstant('DB_NAME'),
    dbUser: getConstant('DB_USER'),
    dbPassword: getConstant('DB_PASSWORD'),
    dbHost: getConstant('DB_HOST'),
    tablePrefix: prefixMatch ? prefixMatch[1] : 'wp_',
  };
}

function maybeUnserializeOption(value) {
  if (typeof value !== 'string') {
    return value;
  }

  const trimmed = value.trim();
  if (!trimmed) {
    return value;
  }

  try {
    return unserialize(trimmed);
  } catch {
    return value;
  }
}

async function readWpFanficOptions(dbConfig) {
  const wpConfig = parseWpConfig();
  const connectionOptions = buildDbConnectionOptions(dbConfig);
  if (!connectionOptions) {
    return {};
  }

  const connection = await mysql.createConnection(connectionOptions);

  try {
    const table = `${wpConfig.tablePrefix}options`;
    const names = [
      'fanfic_use_base_slug',
      'fanfic_base_slug',
      'fanfic_story_path',
      'fanfic_dashboard_slug',
      'fanfic_members_slug',
      'fanfic_chapter_slugs',
      'fanfic_system_page_ids',
      'fanfic_system_page_slugs',
    ];
    const placeholders = names.map(() => '?').join(', ');
    const [rows] = await connection.execute(
      `SELECT option_name, option_value FROM ${table} WHERE option_name IN (${placeholders})`,
      names
    );

    const options = {};
    for (const row of rows) {
      options[row.option_name] = maybeUnserializeOption(row.option_value);
    }

    return options;
  } finally {
    await connection.end();
  }
}

async function findFanficStoryRecord(dbConfig, identifier) {
  const wpConfig = parseWpConfig();
  const connectionOptions = buildDbConnectionOptions(dbConfig);
  if (!connectionOptions || !identifier) {
    return null;
  }

  const connection = await mysql.createConnection(connectionOptions);

  try {
    const table = `${wpConfig.tablePrefix}posts`;
    const numericId = Number.parseInt(String(identifier), 10);
    const query = Number.isFinite(numericId) && String(numericId) === String(identifier).trim()
      ? `SELECT ID, post_name, post_title FROM ${table} WHERE ID = ? AND post_type = 'fanfiction_story' LIMIT 1`
      : `SELECT ID, post_name, post_title FROM ${table} WHERE post_name = ? AND post_type = 'fanfiction_story' LIMIT 1`;
    const params = Number.isFinite(numericId) && String(numericId) === String(identifier).trim()
      ? [numericId]
      : [String(identifier).trim()];
    const [rows] = await connection.execute(query, params);
    return rows[0] || null;
  } finally {
    await connection.end();
  }
}

function deriveFanficAccessProfile(userProfile) {
  const roles = Array.isArray(userProfile?.roles) ? userProfile.roles : [];
  const capabilities = userProfile?.capabilities && typeof userProfile.capabilities === 'object'
    ? userProfile.capabilities
    : {};

  const hasRole = (role) => roles.includes(role);
  const hasCap = (cap) => capabilities[cap] === true || capabilities[cap] === 1 || capabilities[cap] === '1';

  const isWordPressAdmin = hasRole('administrator') || hasCap('manage_options');
  let fanficRole = 'none';

  if (isWordPressAdmin) {
    fanficRole = 'wordpress_admin';
  } else if (hasRole('fanfiction_admin') || hasCap('manage_fanfiction_settings')) {
    fanficRole = 'fanfiction_admin';
  } else if (hasRole('fanfiction_moderator') || hasCap('moderate_fanfiction')) {
    fanficRole = 'fanfiction_moderator';
  } else if (hasRole('fanfiction_author')) {
    fanficRole = 'fanfiction_author';
  } else if (hasRole('fanfiction_reader')) {
    fanficRole = 'fanfiction_reader';
  } else if (hasRole('fanfiction_banned_user')) {
    fanficRole = 'fanfiction_banned_user';
  }

  return {
    isWordPressAdmin,
    fanficRole,
    canModerateFanfiction: isWordPressAdmin || hasCap('moderate_fanfiction'),
    canManageFanfictionSettings: isWordPressAdmin || hasCap('manage_fanfiction_settings'),
    canManageFanfictionTaxonomies: isWordPressAdmin || hasCap('manage_fanfiction_taxonomies'),
    canManageFanfictionUrlConfig: isWordPressAdmin || hasCap('manage_fanfiction_url_config'),
    canManageFanfictionEmails: isWordPressAdmin || hasCap('manage_fanfiction_emails'),
    canManageFanfictionCss: isWordPressAdmin || hasCap('manage_fanfiction_css'),
    canEditOwnFanfictionStories: isWordPressAdmin || hasCap('edit_fanfiction_stories'),
    canEditOthersFanfictionStories: isWordPressAdmin || hasCap('edit_others_fanfiction_stories'),
    canEditOwnFanfictionChapters: isWordPressAdmin || hasCap('edit_fanfiction_chapters'),
    canEditOthersFanfictionChapters: isWordPressAdmin || hasCap('edit_others_fanfiction_chapters'),
  };
}

async function readUserAccessProfile(dbConfig, username) {
  const wpConfig = parseWpConfig();
  const connectionOptions = buildDbConnectionOptions(dbConfig);
  if (!connectionOptions || !username) {
    return null;
  }

  const connection = await mysql.createConnection(connectionOptions);

  try {
    const usersTable = `${wpConfig.tablePrefix}users`;
    const usermetaTable = `${wpConfig.tablePrefix}usermeta`;
    const capabilityKey = `${wpConfig.tablePrefix}capabilities`;

    const [users] = await connection.execute(
      `SELECT ID, user_login, user_email, display_name FROM ${usersTable} WHERE user_login = ? LIMIT 1`,
      [username]
    );

    const user = users[0];
    if (!user) {
      return null;
    }

    const [metaRows] = await connection.execute(
      `SELECT meta_key, meta_value FROM ${usermetaTable} WHERE user_id = ? AND meta_key IN (?, 'fanfic_banned', 'fanfic_original_role')`,
      [user.ID, capabilityKey]
    );

    const meta = Object.fromEntries(metaRows.map((row) => [row.meta_key, row.meta_value]));
    const capabilities = maybeUnserializeOption(meta[capabilityKey]) || {};
    const roles = Object.entries(capabilities)
      .filter(([, enabled]) => enabled === true || enabled === 1 || enabled === '1')
      .map(([role]) => role);

    const profile = {
      id: Number(user.ID),
      username: user.user_login,
      email: user.user_email,
      displayName: user.display_name,
      roles,
      capabilities,
      fanficBanned: meta.fanfic_banned === '1',
      fanficOriginalRole: meta.fanfic_original_role || '',
    };

    profile.derived = deriveFanficAccessProfile(profile);
    return profile;
  } finally {
    await connection.end();
  }
}

async function prompt(rl, question, fallback = '') {
  if (!interactive) {
    return fallback;
  }
  const suffix = fallback ? ` [${fallback}]` : '';
  const answer = (await rl.question(`${question}${suffix}: `)).trim();
  return answer || fallback;
}

function normalizeSiteUrl(input) {
  const value = input.trim();
  const url = new URL(value.endsWith('/') ? value : `${value}/`);
  return url.origin + url.pathname.replace(/\/+$/, '/').replace(/\/$/, '/');
}

function normalizePath(input, fallback) {
  const value = (input || fallback || '').trim();
  if (!value) {
    return '/wp-admin';
  }
  return value.startsWith('/') ? value : `/${value}`;
}

function sanitizeSlug(input, fallback = '') {
  const value = String(input || fallback || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
  return value || fallback;
}

function buildFanficSiteUrl(siteUrl, options = {}) {
  const useBaseSlug = options.fanfic_use_base_slug !== false && options.fanfic_use_base_slug !== '0';
  const baseSlug = useBaseSlug ? sanitizeSlug(options.fanfic_base_slug, 'fanfiction') : '';
  const storyPath = sanitizeSlug(options.fanfic_story_path, 'stories');
  const parts = [baseSlug, storyPath].filter(Boolean).join('/');
  return new URL(`${parts}/`, `${siteUrl.replace(/\/+$/, '')}/`).toString();
}

function buildFanficStoryUrl(siteUrl, options, storySlug) {
  const useBaseSlug = options.fanfic_use_base_slug !== false && options.fanfic_use_base_slug !== '0';
  const baseSlug = useBaseSlug ? sanitizeSlug(options.fanfic_base_slug, 'fanfiction') : '';
  const storyPath = sanitizeSlug(options.fanfic_story_path, 'stories');
  const slug = sanitizeSlug(storySlug, '');
  if (!slug) {
    return '';
  }
  const parts = [baseSlug, storyPath, slug].filter(Boolean).join('/');
  return new URL(`${parts}/`, `${siteUrl.replace(/\/+$/, '')}/`).toString();
}

const FANFICTION_MENU_SELECTOR = '#toplevel_page_fanfiction-manager';
const FANFICTION_MENU_LINK_SELECTOR =
  '#toplevel_page_fanfiction-manager > a, #toplevel_page_fanfiction-manager .wp-submenu a';

function extractFanficAdminMenuLinksFromDom(root = document) {
  const menuRoot = root.querySelector(FANFICTION_MENU_SELECTOR);
  if (!menuRoot) {
    return null;
  }

  const items = Array.from(menuRoot.querySelectorAll(FANFICTION_MENU_LINK_SELECTOR)).map((anchor) => {
    const text = (anchor.textContent || '').replace(/\s+/g, ' ').trim();
    return {
      text,
      href: anchor.getAttribute('href') || '',
      current: anchor.classList.contains('current') || anchor.getAttribute('aria-current') === 'page',
      target: anchor.getAttribute('target') || '',
    };
  });

  return {
    title: (menuRoot.querySelector('.wp-menu-name')?.textContent || 'Fanfiction').trim(),
    items,
  };
}

async function waitForFanficAdminMenu(page, timeoutMs = 30000) {
  await page.waitForSelector(FANFICTION_MENU_SELECTOR, { timeout: timeoutMs });
  return page.evaluate(() => {
    const result = window.__fanficExtractAdminMenuLinks
      ? window.__fanficExtractAdminMenuLinks()
      : null;
    return result;
  });
}

async function collectInstalledThemes(page, siteUrl) {
  const themesUrl = new URL('/wp-admin/themes.php', siteUrl).toString();
  await page.goto(themesUrl, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle').catch(() => {});

  return page.evaluate(() => Array.from(document.querySelectorAll('div.theme')).map((el) => {
    const slug = el.getAttribute('data-slug') || '';
    const name = (el.querySelector('.theme-name')?.textContent || el.textContent || '')
      .replace(/^Active:\s*/i, '')
      .replace(/\s+/g, ' ')
      .trim();
    const author = (el.querySelector('.theme-author')?.textContent || '')
      .replace(/^By\s+/i, '')
      .replace(/\s+/g, ' ')
      .trim();
    const activateLink = el.querySelector('a.button.activate');
    const previewLink = el.querySelector('a.load-customize');
    return {
      slug,
      name,
      author,
      active: el.classList.contains('active'),
      activateHref: activateLink ? activateLink.href : '',
      previewHref: previewLink ? previewLink.href : '',
    };
  }));
}

function printInstalledThemes(themes) {
  console.log('Installed themes:');
  themes.forEach((theme, index) => {
    const status = theme.active ? ' (active)' : '';
    const author = theme.author ? ` by ${theme.author}` : '';
    console.log(`  ${index + 1}. ${theme.name} [${theme.slug}]${status}${author}`);
  });
}

function resolveThemeSelection(input, themes) {
  const value = String(input || '').trim();
  if (!value) {
    return null;
  }

  const numeric = Number.parseInt(value, 10);
  if (Number.isInteger(numeric) && numeric >= 1 && numeric <= themes.length) {
    return themes[numeric - 1];
  }

  const normalized = value.toLowerCase();
  const exactSlug = themes.find((theme) => theme.slug.toLowerCase() === normalized);
  if (exactSlug) {
    return exactSlug;
  }

  const exactName = themes.find((theme) => theme.name.toLowerCase() === normalized);
  if (exactName) {
    return exactName;
  }

  const partial = themes.filter((theme) =>
    theme.slug.toLowerCase().includes(normalized) || theme.name.toLowerCase().includes(normalized)
  );
  if (partial.length === 1) {
    return partial[0];
  }

  return null;
}

async function promptThemeSwitch(rl, page, config) {
  const themes = await collectInstalledThemes(page, config.siteUrl);
  if (!themes.length) {
    console.log('No installed themes were found on the WordPress Themes screen.');
    return;
  }

  printInstalledThemes(themes);

  while (true) {
    const choice = (await prompt(rl, 'Theme to activate (number, slug, or name; blank to skip)', '')).trim();
    if (!choice) {
      return;
    }

    const selected = resolveThemeSelection(choice, themes);
    if (!selected) {
      console.log(`Theme "${choice}" was not found. Enter a listed number, slug, or name.`);
      continue;
    }

    if (selected.active) {
      console.log(`Theme "${selected.name}" is already active.`);
      return;
    }

    const themeCard = page.locator(`div.theme[data-slug="${selected.slug}"]`).first();
    const activateLink = themeCard.locator('a.button.activate').first();
    await activateLink.click();
    await page.waitForLoadState('domcontentloaded').catch(() => {});
    await page.waitForFunction(
      (slug) => {
        const activeTheme = document.querySelector(`div.theme.active[data-slug="${slug}"]`);
        return !!activeTheme;
      },
      selected.slug,
      { timeout: 15000 }
    ).catch(() => {});
    console.log(`Activated theme "${selected.name}" (${selected.slug}).`);
    return;
  }
}

function probePort(host, port, timeoutMs = 2000) {
  return new Promise((resolve) => {
    const socket = net.connect({ host, port });
    let settled = false;

    const done = (result) => {
      if (!settled) {
        settled = true;
        socket.destroy();
        resolve(result);
      }
    };

    socket.setTimeout(timeoutMs);
    socket.once('connect', () => done(true));
    socket.once('timeout', () => done(false));
    socket.once('error', () => done(false));
  });
}

async function ensureDbPort(config, rl) {
  const ok = await probeMysqlConnection(config);
  if (ok) {
    return config;
  }

  if (!interactive) {
    throw new Error('Database connection failed and interactive fallback is unavailable.');
  }

  const fallback = await prompt(
    rl,
    'Database connection failed. Enter a different MySQL socket path or TCP port',
    config.dbSocket || String(config.dbPort || defaultDbPort)
  );
  const fallbackPort = Number.parseInt(fallback, 10);
  if (Number.isFinite(fallbackPort) && fallbackPort > 0) {
    config.dbHost = 'localhost';
    config.dbPort = fallbackPort;
    await writeAuthConfig(config);
    return config;
  }

  config.dbHost = 'localhost';
  config.dbSocket = fallback;
  await writeAuthConfig(config);
  return config;
}

function buildDbConnectionOptions(config) {
  const wpConfig = parseWpConfig();
  if (!wpConfig) {
    return false;
  }

  const useSocket = process.platform !== 'win32';
  const socketPath = useSocket ? (config?.dbSocket || wpConfig.dbSocket || '') : '';
  if (socketPath) {
    return {
      socketPath,
      user: wpConfig.dbUser,
      password: wpConfig.dbPassword,
      database: wpConfig.dbName,
      connectTimeout: 3000,
    };
  }

  return {
    host: config?.dbHost || wpConfig.dbHost || 'localhost',
    port: config?.dbPort || defaultDbPort,
    user: wpConfig.dbUser,
    password: wpConfig.dbPassword,
    database: wpConfig.dbName,
    connectTimeout: 3000,
  };
}

async function probeMysqlConnection(config) {
  const connectionOptions = buildDbConnectionOptions(config);
  if (!connectionOptions) {
    return false;
  }

  let connection;
  try {
    connection = await mysql.createConnection(connectionOptions);
    await connection.ping();
    return true;
  } catch {
    return false;
  } finally {
    if (connection) {
      await connection.end().catch(() => {});
    }
  }
}

async function ensureConfig(rl) {
  const config = await readAuthConfig();

  config.siteUrl = normalizeSiteUrl(await prompt(rl, 'Site URL', config.siteUrl || 'http://localhost'));
  config.wpAdminPath = normalizePath(
    await prompt(rl, 'Login path', config.wpAdminPath || config.loginPath || '/wp-admin'),
    '/wp-admin'
  );
  config.username = config.username || (await prompt(rl, 'WordPress username', 'sammu89'));
  config.password = config.password || (await prompt(rl, 'WordPress password', ''));
  config.dbHost = config.dbHost || 'localhost';
  config.dbPort = Number.parseInt(config.dbPort || defaultDbPort, 10) || defaultDbPort;

  if (!config.username || !config.password) {
    throw new Error('Username and password are required.');
  }

  await ensureDbPort(config, rl);
  config.fanficOptions = {
    ...(await readWpFanficOptions(config)),
    ...config.fanficOptions,
  };
  config.userAccessProfile = await readUserAccessProfile(config, config.username);

  if (interactive) {
    const storyIdentifier = (await prompt(rl, 'Story identifier (ID or slug, optional)', '')).trim();
    if (storyIdentifier) {
      const storyRecord = await findFanficStoryRecord(config, storyIdentifier);
      if (storyRecord) {
        config.storyTarget = {
          id: Number(storyRecord.ID),
          slug: storyRecord.post_name,
          title: storyRecord.post_title,
          url: buildFanficStoryUrl(config.siteUrl, config.fanficOptions, storyRecord.post_name),
        };
      } else {
        config.storyTarget = null;
      }
    }
  }

  await writeAuthConfig(config);
  return config;
}

function findChromeExecutable() {
  for (const candidate of chromeCandidates()) {
    if (candidate && existsSync(candidate)) {
      return candidate;
    }
  }

  return null;
}

async function ensureRemoteDebugChrome() {
  try {
    const response = await fetch(`${cdpUrl}/json/version`);
    if (response.ok) {
      return;
    }
  } catch {
    // Fall through and launch a dedicated browser.
  }

  const chrome = findChromeExecutable();
  if (!chrome) {
    throw new Error('Chrome/Chromium executable not found.');
  }

  const profileDir = path.join(process.env.TEMP || process.cwd(), 'codex-chrome-fanfiction-remote');
  await fs.mkdir(profileDir, { recursive: true });

  const child = spawn(
    chrome,
    [
      '--remote-debugging-port=9222',
      '--remote-debugging-address=127.0.0.1',
      `--user-data-dir=${profileDir}`,
      '--no-first-run',
      '--no-default-browser-check',
      '--new-window',
      'about:blank',
    ],
    {
      detached: true,
      stdio: 'ignore',
      windowsHide: false,
    }
  );

  child.unref();

  const deadline = Date.now() + 15000;
  while (Date.now() < deadline) {
    try {
      const response = await fetch(`${cdpUrl}/json/version`);
      if (response.ok) {
        return;
      }
    } catch {
      // retry
    }
    await new Promise((resolve) => setTimeout(resolve, 500));
  }

  throw new Error('Chrome started but DevTools endpoint did not become available on port 9222.');
}

async function connectBrowser() {
  await ensureRemoteDebugChrome();
  return chromium.connectOverCDP(cdpUrl);
}

async function login(page, config) {
  const loginUrl = new URL(config.wpAdminPath, config.siteUrl).toString();
  await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });

  const usernameSelectors = ['#user_login', 'input[name="log"]', 'input[type="email"]'];
  const passwordSelectors = ['#user_pass', 'input[name="pwd"]', 'input[type="password"]'];
  const submitSelectors = ['#wp-submit', 'button[type="submit"]', 'input[type="submit"]'];

  const usernameField = page.locator(usernameSelectors.join(', ')).first();
  if (await usernameField.count()) {
    await usernameField.fill(config.username);
    const passwordField = page.locator(passwordSelectors.join(', ')).first();
    if (await passwordField.count()) {
      await passwordField.fill(config.password);
    }
    const submitButton = page.locator(submitSelectors.join(', ')).first();
    if (await submitButton.count()) {
      await submitButton.click();
    } else {
      await page.keyboard.press('Enter');
    }

    await page.waitForLoadState('networkidle').catch(() => {});
  }
}

async function main() {
  if (process.env.LIVE_SESSION_SELF_TEST === '1') {
    const net = await import('node:net');
    const authConfig = await readAuthConfig();
    const dbConfig = {
      dbHost: authConfig.dbHost || 'localhost',
      dbPort: authConfig.dbPort,
      dbSocket: authConfig.dbSocket,
    };
    const options = await readWpFanficOptions(dbConfig);
    const userAccessProfile = await readUserAccessProfile(dbConfig, 'sammu89').catch(() => null);
    let storyCount = null;
    let firstStory = null;
    try {
      const connectionOptions = buildDbConnectionOptions(dbConfig);
      const connection = await mysql.createConnection(connectionOptions);
      const wpConfig = parseWpConfig();
      const table = `${wpConfig.tablePrefix}posts`;
      const [countRows] = await connection.execute(`SELECT COUNT(*) AS total FROM ${table} WHERE post_type = 'fanfiction_story'`);
      storyCount = countRows[0]?.total ?? null;
      const [rows] = await connection.execute(`SELECT ID, post_name, post_title FROM ${table} WHERE post_type = 'fanfiction_story' ORDER BY ID DESC LIMIT 1`);
      firstStory = rows[0] || null;
      await connection.end();
    } catch {
      // Leave story info null when the DB is unavailable.
    }

    const server = net.createServer();
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    const { port } = server.address();
    const openProbe = await probePort('127.0.0.1', port);
    const closedProbe = await probePort('127.0.0.1', port + 1);
    server.close();

    console.log(JSON.stringify({
      siteUrl: normalizeSiteUrl('http://localhost'),
      wpAdminPath: normalizePath('wp-admin', '/wp-admin'),
      fanficOptions: options,
      userAccessProfile,
      storyCount,
      firstStory,
      openProbe,
      closedProbe,
      authExists: existsSync(authPath),
    }));
    return;
  }

  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });

  try {
    const config = await ensureConfig(rl);
    const browser = await connectBrowser();
    const context = browser.contexts()[0] || (await browser.newContext());
    const page = context.pages()[0] || (await context.newPage());

    await page.addInitScript(() => {
      window.__fanficExtractAdminMenuLinks = () => {
        const selector = '#toplevel_page_fanfiction-manager';
        const linkSelector = '#toplevel_page_fanfiction-manager > a, #toplevel_page_fanfiction-manager .wp-submenu a';
        const menuRoot = document.querySelector(selector);
        if (!menuRoot) {
          return null;
        }
        const items = Array.from(menuRoot.querySelectorAll(linkSelector)).map((anchor) => ({
          text: (anchor.textContent || '').replace(/\s+/g, ' ').trim(),
          href: anchor.getAttribute('href') || '',
          current: anchor.classList.contains('current') || anchor.getAttribute('aria-current') === 'page',
          target: anchor.getAttribute('target') || '',
        }));
        return {
          title: (menuRoot.querySelector('.wp-menu-name')?.textContent || 'Fanfiction').trim(),
          items,
        };
      };
    });

    await login(page, config);
    await promptThemeSwitch(rl, page, config);
    await page.goto(new URL(config.wpAdminPath, config.siteUrl).toString(), { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle').catch(() => {});
    const menu = await waitForFanficAdminMenu(page).catch(() => null);

    console.log(`Connected to ${cdpUrl}`);
    console.log(`Opened ${new URL(config.wpAdminPath, config.siteUrl).toString()}`);
    const fanficBaseUrl = buildFanficSiteUrl(config.siteUrl, config.fanficOptions);
    console.log(`Fanfiction base URL: ${fanficBaseUrl}`);
    if (config.userAccessProfile) {
      console.log(
        `User access: ${config.userAccessProfile.username} roles=${config.userAccessProfile.roles.join(', ') || '(none)'} fanficRole=${config.userAccessProfile.derived.fanficRole}`
      );
    }
    if (config.storyTarget) {
      console.log(
        `Story target: ${config.storyTarget.title} (#${config.storyTarget.id}) -> ${config.storyTarget.url}`
      );
    }
    if (menu && Array.isArray(menu.items)) {
      console.log(
        `Detected ${menu.title} admin menu: ${menu.items.map((item) => `${item.text} -> ${item.href}`).join(', ')}`
      );
    } else {
      console.log('Fanfiction admin menu was not detected on the current admin page.');
    }
    console.log('The browser remains open because it is a detached Chrome session.');
    if (process.stdin.isTTY) {
      console.log('Press Enter to exit this helper.');
      await rl.question('');
    } else {
      console.log('Non-interactive input detected; exiting without waiting.');
    }
  } finally {
    rl.close();
  }
}

const isMainModule = process.argv[1] && fileURLToPath(import.meta.url) === path.resolve(process.argv[1]);

if (isMainModule) {
  main().catch((error) => {
    console.error(error instanceof Error ? error.message : error);
    process.exitCode = 1;
  });
}

export {
  buildFanficSiteUrl,
  buildFanficStoryUrl,
  ensureConfig,
  ensureDbPort,
  findFanficStoryRecord,
  login,
  maybeUnserializeOption,
  normalizePath,
  normalizeSiteUrl,
  parseWpConfig,
  probePort,
  readAuthConfig,
  readUserAccessProfile,
  readWpFanficOptions,
  writeAuthConfig,
};
