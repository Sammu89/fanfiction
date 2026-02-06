# Fanfiction Manager Setup Wizard Audit and Optimization Plan

Date: 2026-02-06
Repository: `wp-content/plugins/fanfiction`
Auditor: Codex (GPT-5)

## 1. Goal and Scope

This document audits how the setup wizard behaves across all entry points and lifecycle paths:

- Plugin activation
- Wizard execution and completion
- Wizard re-run (normal and forced)
- Delete data and reinitialize path
- Homepage setting checks and repair actions

The primary focus is homepage behavior and why homepage-related settings do not reliably persist.

This report is designed so an AI coding agent can read only this file and fully understand:

- Current architecture and control flow
- Side effects at each step
- Data model and option writes
- Failure modes and root causes
- A concrete optimized target architecture
- Migration and implementation plan
- Test plan and acceptance criteria

## 2. Executive Summary

The wizard mostly works, but homepage persistence is unstable because homepage behavior is distributed across multiple classes with overlapping responsibilities and inconsistent assumptions.

Key root causes:

1. Homepage state is compressed into coarse scenarios (`scenario_3`, `scenario_4`) that do not preserve the selected homepage source semantics.
2. Homepage checker/fixer logic assumes scenario 4 always means `show_on_front=page` with the plugin main page, which conflicts with valid user selections (existing page, posts archive).
3. Homepage writes happen from multiple paths (`create_system_pages`, URL settings homepage save, fix action), without one shared source of truth function.
4. Wizard step 2 UI exposes many slug controls, but wizard save persists only a subset, leading to user expectation mismatch and hidden drift.
5. Additional consistency bugs exist in URL slug handling (wrong option source for page slugs, dynamic slug persistence mismatch, key mismatch for password reset slug).

Result: homepage settings can appear to "not stick", be flagged as externally changed when they are valid, and be reset by the plugin to an unintended state.

## 3. System Map

### 3.1 Main Files Involved

- `fanfiction-manager.php`
- `includes/class-fanfic-core.php`
- `includes/class-fanfic-wizard.php`
- `includes/class-fanfic-url-config.php`
- `includes/class-fanfic-templates.php`
- `includes/class-fanfic-url-manager.php`
- `includes/class-fanfic-url-schema.php`
- `includes/class-fanfic-settings.php`
- `assets/js/fanfic-wizard.js`

### 3.2 Primary Domain Concepts

- Wizard completion state: `fanfic_wizard_completed`
- URL mode: `fanfic_use_base_slug` (1/0)
- Homepage intent:
  - `fanfic_main_page_mode` (`stories_homepage` or `custom_homepage`)
  - `fanfic_homepage_source` (`fanfiction_page`, `wordpress_archive`, `existing_page`)
  - `fanfic_homepage_source_id`
- WP homepage runtime:
  - `show_on_front` (`posts` or `page`)
  - `page_on_front` (page ID or 0)
- System page records:
  - `fanfic_system_page_ids`
  - `fanfic_system_page_slugs`

## 4. Trigger Paths and Lifecycle

### 4.1 Activation

Entry:

- `fanfiction-manager.php:45` registers activation hook to `Fanfic_Core::activate()`.
- Activation implementation in `includes/class-fanfic-core.php:1297`.

Activation side effects:

- Verifies permalink requirement and templates.
- Loads required classes for activation tasks.
- Creates DB tables via `Fanfic_Database_Setup::init(false)` (classification deferred).
- Sets transient `fanfic_skip_classification` (300s).
- Registers post types/taxonomies, creates roles, flushes rewrites.
- Sets options:
  - `fanfic_activated=true`
  - `fanfic_version`
- Schedules cron jobs.

Notably:

- No hard redirect to wizard on activation in current flow.
- Wizard prompting is notice-driven (see section 4.2).

### 4.2 Wizard Entry after Activation

Wizard menu and handlers are initialized in admin through `Fanfic_Wizard::get_instance()` (`includes/class-fanfic-core.php:190`).

Prompt behavior:

- `includes/class-fanfic-templates.php:355` displays a warning notice with "Run Setup Wizard" when `fanfic_wizard_completed` is false.
- `check_wizard_redirect()` exists (`includes/class-fanfic-wizard.php:229`) but currently contains no redirect behavior beyond guard clauses.

### 4.3 Re-run Wizard

Entry points:

- Settings tab button: `includes/class-fanfic-settings.php:1462`
- Wizard choice screen "Run Wizard Again": `force=true` link (`includes/class-fanfic-wizard.php:495`)

Behavior:

- Wizard page checks `fanfic_wizard_completed` + `all_pages_exist`.
- If completed and healthy and not `force=true`, renders a choice screen instead of immediate step 1 (`includes/class-fanfic-wizard.php:405`).

### 4.4 Delete Data then Reinitialize

Entry:

- Settings action `admin_post_fanfic_delete_data` (`includes/class-fanfic-settings.php:66`).
- Handler `handle_delete_data()` (`includes/class-fanfic-settings.php:3181`).

Behavior:

- Deletes fanfiction content posts, system pages, menu, plugin tables, and options matching `fanfic%`.
- Redirects to wizard page: `admin.php?page=fanfic-setup-wizard` (`includes/class-fanfic-settings.php:3296`).

Effect:

- Plugin returns to pre-wizard state and must be configured again.

## 5. Wizard Runtime: Step-by-Step Side Effects

Wizard has 5 steps (`includes/class-fanfic-wizard.php:46`, step definition around line 84).

Front-end controller:

- `assets/js/fanfic-wizard.js`
- Next button saves step through AJAX and navigates.
- Step 1 first calls classification-table creation endpoint, then saves step data.

### 5.1 Step 1 (Welcome/Homepage)

Render:

- `Fanfic_URL_Config::render_homepage_settings(true)` (`includes/class-fanfic-wizard.php:550`).

On Next:

1. Creates classification tables + seeds data via `fanfic_wizard_create_tables`:
   - `includes/class-fanfic-wizard.php:866`
2. Saves homepage selection via `save_homepage_settings()`:
   - `includes/class-fanfic-wizard.php:968`
   - `includes/class-fanfic-url-config.php:1774`

Writes:

- `fanfic_main_page_mode`
- If custom homepage selected:
  - `fanfic_homepage_source`
  - `fanfic_homepage_source_id`

No direct writes yet to:

- `show_on_front`
- `page_on_front`

Those are applied later at page creation/commit time.

### 5.2 Step 2 (URL Settings)

Render:

- URL mode radios + slug fields (`includes/class-fanfic-wizard.php:580-583`, `includes/class-fanfic-url-config.php:1591`, `183+`).

On Next:

- Saves via `save_wizard_url_settings()` (`includes/class-fanfic-wizard.php:982-985`, `includes/class-fanfic-url-config.php:1544`).

Actually persisted in wizard path:

- `fanfic_use_base_slug` (stored as int 1/0)
- `fanfic_base_slug` (when mode is enabled)
- `fanfic_story_path`

Not persisted in wizard path despite being shown in step 2 UI:

- Chapter slugs (`fanfic_chapter_slugs`)
- Most system page slugs (`fanfic_system_page_slugs`)
- Dynamic slugs in coherent way

This mismatch is a UX/data consistency issue.

### 5.3 Step 3 (User Roles)

On Next:

- Stores temporary arrays:
  - `fanfic_wizard_moderators`
  - `fanfic_wizard_admins`

Later consumed at completion for capability/role assignment.

### 5.4 Step 4 (Taxonomy and Classification Features)

On Next:

- Stores default term arrays for genre/status in temp options:
  - `fanfic_wizard_genre_terms`
  - `fanfic_wizard_status_terms`
- Writes settings toggles through `Fanfic_Settings::update_setting(...)`:
  - fandom classification
  - warnings
  - language classification
  - sexual/pornographic content flags

### 5.5 Step 5 (Complete)

On Complete button:

- AJAX `fanfic_wizard_complete` (`includes/class-fanfic-wizard.php:1075`).

Execution order:

1. Read base slug option.
2. Create or update system pages via `Fanfic_Templates::create_system_pages($base_slug)`.
3. Create taxonomy terms from temp options.
4. Assign user roles from temp options.
5. Optionally create sample stories.
6. Flush rewrite rules.
7. Validate required pages exist.
8. Mark wizard complete:
   - `fanfic_wizard_completed=true`
   - delete `fanfic_show_wizard`
9. Cleanup temp wizard role options.

Critical fact:

- Homepage WP options are indirectly set inside `create_system_pages()` depending on mode/source.

## 6. Homepage Logic in Current Architecture

### 6.1 Where Homepage Is Written

Homepage writes occur in multiple places:

1. `Fanfic_Templates::create_system_pages()` (`includes/class-fanfic-templates.php:704+`)
   - Scenario 3 branch writes `show_on_front=posts`.
   - Scenario 4 branch writes based on `fanfic_homepage_source`.
   - Writes `fanfic_homepage_mode` as scenario marker.

2. `Fanfic_URL_Config::save_homepage()` (`includes/class-fanfic-url-config.php:1811+`)
   - Settings page action for homepage form.
   - Writes `fanfic_main_page_mode`, and if no base slug, writes `show_on_front`, `page_on_front`, `fanfic_homepage_mode`, and source options.

3. `Fanfic_Templates::fix_homepage_settings()` (`includes/class-fanfic-templates.php:1246+`)
   - "Fix Now" action.
   - Writes homepage values based on scenario assumptions.

### 6.2 Where Homepage Is Validated

`Fanfic_Templates::check_homepage_settings()` (`includes/class-fanfic-templates.php:1132+`) checks for external changes by evaluating:

- `fanfic_homepage_mode`
- `show_on_front`
- `page_on_front`

and setting transient `fanfic_homepage_changed`.

### 6.3 Scenario Model Used

- `scenario_3`: no base slug + stories homepage
- `scenario_4`: no base slug + custom homepage

But current implementation overloads `scenario_4` to include cases that are not "page on front = plugin main".

## 7. Confirmed Defects and Risks

### 7.1 Homepage checker/fixer invalid for non-main custom sources

Evidence:

- Checker for scenario 4 expects `show_on_front=page` and `page_on_front=main page ID` (`includes/class-fanfic-templates.php:1163-1168`).
- Fix action for scenario 4 forces `page_on_front` to plugin main page (`includes/class-fanfic-templates.php:1263-1268`).

Problem:

If user selects `existing_page` as homepage source (valid), checker flags false drift and fix rewrites to plugin main page.

Impact:

- "Homepage does not stick"
- False warning loops
- User-selected homepage overwritten

### 7.2 `wordpress_archive` incorrectly encoded as `scenario_4`

Evidence:

- `save_homepage()` sets `fanfic_homepage_mode='scenario_4'` for `wordpress_archive` source (`includes/class-fanfic-url-config.php:1856-1862`).

Problem:

Scenario 4 checker expects `show_on_front=page`, but WP archive uses `posts`.

Impact:

- Guaranteed mismatch
- Endless changed-homepage notice
- Fix action can apply wrong target

### 7.3 No-base + stories-homepage model mismatch

Evidence:

- Page creation sets scenario 3 to `show_on_front=posts` (`includes/class-fanfic-templates.php:772-776`).
- Archive query adjustment in core relies on `is_page(main)` for stories-homepage mode (`includes/class-fanfic-core.php:603-613`).

Problem:

If front is posts, request context is not plugin main page, so template/query behavior can diverge from intended stories-homepage experience.

Impact:

- Inconsistent archive rendering behavior
- Difficult-to-debug front page routing

### 7.4 Wizard step 2 persists only partial data

Evidence:

- `save_wizard_url_settings()` only saves mode, base slug, story path (`includes/class-fanfic-url-config.php:1544-1588`).
- Step 2 renders many additional slug controls (`includes/class-fanfic-url-config.php:183+`, especially sections around lines 291+ and 351+).

Impact:

- User expects all edited fields to persist in wizard, but they do not.
- Re-run/edit cycles produce confusion and drift.

### 7.5 Wrong option source when rendering system page slugs

Evidence:

- `render_form_fields()` assigns `$page_slugs = get_option('fanfic_system_page_ids', array())` (`includes/class-fanfic-url-config.php:185`).
- Later uses `$page_slugs[$key]` as a slug value (`includes/class-fanfic-url-config.php:413-414`).

Problem:

`fanfic_system_page_ids` contains numeric IDs, not slugs.

Impact:

- Potentially displays IDs in slug fields.
- Could feed wrong data into save flow if submitted.

### 7.6 Dynamic slug persistence mismatch

Evidence:

- URL manager loads dynamic slugs from `fanfic_dashboard_slug` and `fanfic_members_slug` (`includes/class-fanfic-url-manager.php:122-124`).
- `update_slugs()` writes to `fanfic_dynamic_page_slugs` (`includes/class-fanfic-url-manager.php:1094-1096`), which is not read by loader.

Impact:

- Dynamic slug updates can silently fail to affect runtime behavior.

### 7.7 Key mismatch: `password_reset` vs `password-reset`

Evidence:

- URL config definition uses `password_reset` (`includes/class-fanfic-url-config.php:394`).
- URL schema and templates use `password-reset` (`includes/class-fanfic-url-schema.php:138`, `includes/class-fanfic-templates.php:846-849`).

Impact:

- Save/read mismatch risk for password reset slug.
- Potential stale or ignored values.

### 7.8 Wizard redirect path is notice-only, not strict flow

Evidence:

- `check_wizard_redirect()` has no redirect action logic (`includes/class-fanfic-wizard.php:229-245`).
- Setup prompt provided through notices (`includes/class-fanfic-templates.php:355+`).

Risk:

- Admin may continue using plugin in partial setup state if they ignore notices.

## 8. Why Homepage Appears Not to Stick

This is the direct causal chain:

1. User picks a valid no-base custom source (existing page or posts archive).
2. Plugin stores coarse scenario mode (`scenario_4`) not source-specific expectation.
3. Checker assumes scenario 4 means plugin main page on front.
4. Checker marks mismatch even when user setting is valid.
5. Fix action rewrites homepage to plugin main page.
6. User experiences recurring override and believes homepage preference does not persist.

Secondary contributors:

- Multiple write locations with divergent logic.
- Rebuild/create page path mutates homepage state implicitly.

## 9. Optimization Target Architecture

### 9.1 Principle: Stage then Commit

Adopt a two-phase wizard model:

- Phase A: Draft capture per step
- Phase B: Single commit at completion

Do not apply live homepage/system mutations during intermediate steps.

### 9.2 Single Source of Truth for Homepage

Implement one normalized homepage state object, for example:

```php
[
  'use_base_slug' => 0|1,
  'main_page_mode' => 'stories_homepage'|'custom_homepage',
  'homepage_source' => 'fanfiction_page'|'wordpress_archive'|'existing_page',
  'homepage_source_id' => int,
  'main_page_id' => int,
]
```

Create one resolver function:

- Input: normalized state
- Output: expected WP homepage tuple
  - `show_on_front`
  - `page_on_front`

Use this same resolver in:

- Wizard commit
- URL settings homepage save
- Homepage checker
- Homepage fix action

This removes scenario ambiguity.

### 9.3 Decouple Page Sync from Homepage Sync

Current `create_system_pages()` both creates pages and mutates homepage settings.

Target:

- `sync_system_pages(...)` only handles pages/menus/IDs/slugs.
- `sync_homepage_settings(...)` only handles `show_on_front/page_on_front` and related homepage metadata.

Commit order:

1. Validate draft
2. Persist options
3. Sync pages
4. Sync homepage using resolved final state
5. Flush rewrites
6. Mark wizard completed

### 9.4 Canonical State Instead of `scenario_3/4`

Deprecate coarse mode markers for logic.

Keep backward-compatible migration by deriving expected behavior from explicit options:

- `fanfic_use_base_slug`
- `fanfic_main_page_mode`
- `fanfic_homepage_source`
- `fanfic_homepage_source_id`

### 9.5 Strict Option Contracts

Normalize keys and storage:

- Use `password-reset` consistently.
- System page slugs must come from `fanfic_system_page_slugs` only.
- Dynamic slugs: choose one storage strategy and use it everywhere.
  - Preferred: individual options (`fanfic_dashboard_slug`, `fanfic_members_slug`) for consistency with loader.

### 9.6 Wizard UI/Data Alignment

If step 2 displays all slug fields, step 2 save must persist all those fields.

Alternative:

- Reduce step 2 UI to only fields actually persisted at wizard stage.

### 9.7 Re-run Safety

On forced rerun:

- Keep current content/posts intact.
- Recompute and apply URL/homepage consistently from newly committed configuration.
- Keep explicit migration logs (admin notice/report).

## 10. Proposed Implementation Plan

### Phase 1: Stabilize Homepage Logic

1. Create `Fanfic_Homepage_State` helper with:
   - `get_current_state()`
   - `resolve_wp_front_page_target($state)`
   - `apply_wp_front_page_target($target)`
   - `is_wp_front_page_in_sync($state)`
2. Replace logic in:
   - `save_homepage()`
   - `check_homepage_settings()`
   - `fix_homepage_settings()`
   - wizard completion path
3. Stop relying on `scenario_3/4` for checks and fixes.

### Phase 2: Page Creation Separation

1. Refactor `create_system_pages()` into:
   - page sync only
2. Move homepage writes out of page sync.
3. Ensure wizard completion calls homepage sync explicitly after page sync.

### Phase 3: Option Key and Storage Fixes

1. Fix `render_form_fields()` to read `fanfic_system_page_slugs` for slug values.
2. Normalize `password_reset` key to `password-reset` with migration shim.
3. Unify dynamic slug update/read path.

### Phase 4: Wizard Draft and Commit

1. Introduce `fanfic_wizard_draft` option (single array).
2. Each step writes into draft only.
3. Complete action validates draft and applies all changes atomically in order.
4. Cleanup draft on success or explicit cancel/reset.

### Phase 5: Guardrails and Telemetry

1. Add structured debug logging around homepage resolver decisions.
2. Add admin diagnostics page/section showing:
   - stored intent
   - resolved expected WP front target
   - actual WP front target
   - sync status

## 11. Recommended Data Model

### 11.1 Persisted Configuration

- `fanfic_use_base_slug` (int)
- `fanfic_base_slug` (string)
- `fanfic_story_path` (string)
- `fanfic_chapter_slugs` (array)
- `fanfic_system_page_slugs` (array)
- `fanfic_dashboard_slug` (string)
- `fanfic_members_slug` (string)
- `fanfic_main_page_mode` (string)
- `fanfic_homepage_source` (string)
- `fanfic_homepage_source_id` (int)
- `fanfic_system_page_ids` (array)

### 11.2 Deprecated/Transitional

- `fanfic_homepage_mode` (`scenario_3/4`) should become legacy/read-only fallback during migration only.

### 11.3 Wizard Temporary

- Replace multiple temporary keys with one `fanfic_wizard_draft` object.

## 12. Validation and Test Matrix

### 12.1 Core Homepage Combinations

Test each with fresh wizard run and rerun:

1. Base on + custom homepage + fanfiction page source
2. Base on + stories homepage
3. Base off + custom homepage + fanfiction page source
4. Base off + custom homepage + existing page source
5. Base off + custom homepage + wordpress archive source
6. Base off + stories homepage

For each case verify:

- Expected values of `show_on_front` and `page_on_front`
- No false "homepage changed" notice
- Fix action preserves intended source semantics
- URLs resolve and template behavior matches mode

### 12.2 Transitions

1. Base on -> base off
2. Base off -> base on
3. Custom -> stories
4. Stories -> custom
5. Existing page source changed to another page
6. Source changed to/from wordpress archive

Verify:

- Page parent relationships expected
- Canonical front behavior updated once and stable
- No loops in checker/fixer

### 12.3 Wizard Data Integrity

1. Step 2 edits all slug fields then complete.
2. Confirm every edited field persisted.
3. Confirm generated pages use persisted slugs.

### 12.4 Delete Data Path

1. Run delete data.
2. Confirm wizard state reset.
3. Confirm clean reconfiguration works with no stale homepage markers.

## 13. Migration Plan

On plugin upgrade introducing the fix:

1. Detect legacy state where `fanfic_homepage_mode` exists.
2. Derive canonical homepage intent from explicit options; if missing, infer safely:
   - If `show_on_front=posts`: source likely `wordpress_archive` or stories mode.
   - If `show_on_front=page`: map to `existing_page` if `page_on_front` is not plugin main.
3. Write normalized homepage source options.
4. Keep legacy scenario for backward compatibility but do not use for new checks.

## 14. AI Agent Implementation Notes

When implementing from this report:

1. Make homepage behavior deterministic from explicit options only.
2. Ensure checker and fixer call the same resolver used at save/commit time.
3. Remove hidden side effects from page creation where possible.
4. Add regression tests first for the failing no-base custom source cases.
5. Keep changes backward compatible for existing installs.

## 15. Quick Reference: Important Evidence Pointers

- Activation hook: `fanfiction-manager.php:45`
- Activation logic: `includes/class-fanfic-core.php:1297`
- Wizard save/complete handlers: `includes/class-fanfic-wizard.php:910`, `includes/class-fanfic-wizard.php:1075`
- Step 1 save homepage intent: `includes/class-fanfic-url-config.php:1774`
- Step 2 save subset only: `includes/class-fanfic-url-config.php:1544`
- Page creation + homepage writes: `includes/class-fanfic-templates.php:704`
- Homepage checker: `includes/class-fanfic-templates.php:1132`
- Homepage fixer: `includes/class-fanfic-templates.php:1246`
- URL settings homepage saver: `includes/class-fanfic-url-config.php:1811`
- System slug render source bug: `includes/class-fanfic-url-config.php:185`, `includes/class-fanfic-url-config.php:413`
- Dynamic slug update/read mismatch: `includes/class-fanfic-url-manager.php:122`, `includes/class-fanfic-url-manager.php:1095`
- Password reset key mismatch:
  - `includes/class-fanfic-url-config.php:394`
  - `includes/class-fanfic-url-schema.php:138`

## 16. Final Recommendation

Implement homepage-state normalization first. That change alone will address the most visible "homepage does not stick" failures.

Then complete structural cleanup:

- unify save/check/fix logic,
- align wizard UI with persisted data,
- normalize slug key/storage consistency,
- and move to draft-then-commit wizard orchestration.

This sequence provides the fastest path to user-visible stability while reducing long-term maintenance complexity.

## 17. User-Confirmed Target Flow (Must Implement Exactly)

This section records the explicit product requirement confirmed by the site owner and supersedes ambiguous behavior.

### 17.1 Save Behavior by Step

1. Step 1 (homepage intent): save user choices immediately to staged wizard config.
2. Step 2 (URL mode/slugs): save user choices immediately to staged wizard config.
3. Step 3 (admins/moderators): this step is special.
   - Validation must be real (not only staged).
   - Role assignment/validation logic effectively occurs at this step (not deferred like other config choices).
4. Step 4 (taxonomy/feature toggles): save user choices immediately to staged wizard config.
5. Step 5 (complete): no hidden changes before user confirmation; this step is for synthesis + explicit final confirmation.

### 17.2 Step 5 Synthesis and Confirmation Requirements

Step 5 must display a complete synthesis of what will be deployed, including at minimum:

- URL mode (`fanfic_use_base_slug`) and base slug value
- homepage mode/source selection
- resulting expected WP homepage target (`show_on_front` and `page_on_front` expectation)
- system page creation/update summary
- selected taxonomy feature toggles
- sample-data choice
- role actions already validated/performed at step 3

User must explicitly confirm at step 5 before final deployment/commit is executed.

### 17.3 Final Verification Gates (hard checks before marking complete)

Before setting `fanfic_wizard_completed=true`, code must verify:

1. Base slug mode/value are truly persisted and match selected config.
2. Homepage settings are truly applied and match expected resolved target.
   - `show_on_front` value matches expected.
   - `page_on_front` matches expected when homepage is a page.
3. If verification fails, completion must stop and return actionable error details to user.

### 17.4 UX and Safety Guarantees

- Each step should preserve user input reliably when navigating forward/back.
- Step 5 should never silently mutate selected values before confirmation.
- If deployment fails verification, wizard remains incomplete and provides a repair path.
- Confirmation summary should be deterministic and derived from staged config + resolved runtime IDs.
