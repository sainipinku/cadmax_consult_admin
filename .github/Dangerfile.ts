// Dangerfile.ts
// Runs as part of the CI pipeline to enforce PR hygiene rules.
// Install: npm install --save-dev danger @types/danger

import { danger, warn, fail, message, markdown } from "danger";

const { github } = danger;
const pr = github.pr;
const modified = danger.git.modified_files;
const created = danger.git.created_files;
const deleted = danger.git.deleted_files;
const allFiles = [...modified, ...created];

// ── 1. PR description ──────────────────────────────────────────────────────
if (!pr.body || pr.body.trim().length < 30) {
  fail(
    "📝 **PR description is too short.** Please add a meaningful description " +
      "(what changed, why, and how to test it)."
  );
}

// ── 2. PR size warning ─────────────────────────────────────────────────────
const BIG_PR_THRESHOLD = 600;
const totalChanges =
  (pr.additions ?? 0) + (pr.deletions ?? 0);

if (totalChanges > BIG_PR_THRESHOLD) {
  warn(
    `📦 **Large PR detected** (${totalChanges} lines changed). ` +
      "Consider splitting it into smaller, focused PRs for easier review."
  );
}

// ── 3. Test coverage for new source files ──────────────────────────────────
const sourceFiles = created.filter(
  (f) =>
    (f.startsWith("src/") || f.startsWith("lib/")) &&
    !f.includes("__tests__") &&
    !f.includes(".test.") &&
    !f.includes(".spec.")
);
const newTestFiles = created.filter(
  (f) => f.includes(".test.") || f.includes(".spec.")
);

if (sourceFiles.length > 0 && newTestFiles.length === 0) {
  warn(
    `🧪 **No test files added** for new source files:\n${sourceFiles
      .map((f) => `- \`${f}\``)
      .join("\n")}\n\nPlease add corresponding tests.`
  );
}

// ── 4. Changelog check ─────────────────────────────────────────────────────
const hasChangelog =
  modified.includes("CHANGELOG.md") || created.includes("CHANGELOG.md");
const isChore =
  pr.title.startsWith("chore") || pr.title.startsWith("ci");

if (!hasChangelog && !isChore) {
  warn(
    "📋 **CHANGELOG.md not updated.** " +
      "For user-facing changes, please document them in CHANGELOG.md."
  );
}

// ── 5. Prevent committing to main directly ────────────────────────────────
if (pr.base.ref === "main" && pr.head.ref === "main") {
  fail("🚫 **Direct push to `main` is not allowed.** Use a feature branch.");
}

// ── 6. Warn on TODO / FIXME left in new code ─────────────────────────────
const todoPattern = /\/\/\s*(TODO|FIXME|HACK|XXX)/gi;

async function checkForTodos() {
  for (const file of allFiles) {
    if (!file.match(/\.(ts|tsx|js|jsx)$/)) continue;
    const diff = await danger.git.diffForFile(file);
    if (!diff) continue;
    const addedLines = diff.added
      .split("\n")
      .filter((line) => line.startsWith("+"));
    const todos = addedLines.filter((line) => todoPattern.test(line));
    if (todos.length > 0) {
      warn(
        `⚠️ **TODO/FIXME found in \`${file}\`:**\n${todos
          .map((l) => `> \`${l.slice(1).trim()}\``)
          .join("\n")}`
      );
    }
  }
}

// ── 7. Lock file consistency ───────────────────────────────────────────────
const packageChanged =
  modified.includes("package.json") || created.includes("package.json");
const lockChanged =
  modified.includes("package-lock.json") ||
  modified.includes("yarn.lock") ||
  modified.includes("pnpm-lock.yaml");

if (packageChanged && !lockChanged) {
  fail(
    "🔒 **`package.json` was modified but no lock file was updated.** " +
      "Run `npm install` (or your package manager equivalent) and commit the updated lock file."
  );
}

// ── 8. No console.log in production code ──────────────────────────────────
async function checkForConsoleLogs() {
  const productionFiles = allFiles.filter(
    (f) =>
      f.match(/\.(ts|tsx|js|jsx)$/) &&
      !f.includes(".test.") &&
      !f.includes(".spec.") &&
      !f.includes("__tests__")
  );

  for (const file of productionFiles) {
    const diff = await danger.git.diffForFile(file);
    if (!diff) continue;
    const addedLines = diff.added
      .split("\n")
      .filter(
        (line) => line.startsWith("+") && /console\.(log|debug|info)/.test(line)
      );
    if (addedLines.length > 0) {
      warn(
        `🖥️ **\`console.log\` found in \`${file}\`** — remove before merging:\n` +
          addedLines.map((l) => `> \`${l.slice(1).trim()}\``).join("\n")
      );
    }
  }
}

// ── 9. Summary table ──────────────────────────────────────────────────────
markdown(`
## 📊 PR Summary
| Metric | Value |
|---|---|
| Files changed | ${allFiles.length} |
| Lines added | ${pr.additions} |
| Lines deleted | ${pr.deletions} |
| New files | ${created.length} |
| Deleted files | ${deleted.length} |
| New test files | ${newTestFiles.length} |
`);

// Run async checks
(async () => {
  await checkForTodos();
  await checkForConsoleLogs();
})();
