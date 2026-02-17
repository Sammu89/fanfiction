import { PurgeCSS } from "purgecss";
import fs from "fs";
import path from "path";
import { createRequire } from "module";

const require = createRequire(import.meta.url);
const purgeConfig = require("./purgecss.config.js");

const purgeCSSResult = await new PurgeCSS().purge(purgeConfig);

if (!purgeCSSResult.length) {
  throw new Error("PurgeCSS did not return a result.");
}

const outputDir = purgeConfig.output;
if (!fs.existsSync(outputDir)) {
  fs.mkdirSync(outputDir, { recursive: true });
}

fs.writeFileSync(
  path.join(outputDir, path.basename(purgeConfig.css[0])),
  purgeCSSResult[0].css
);

console.log("Purged CSS generated successfully.");
