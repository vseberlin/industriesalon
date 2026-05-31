const { execFileSync } = require("node:child_process");
const path = require("node:path");

const repoRoot = path.resolve(__dirname, "../../..");

function runWp(args) {
  return execFileSync("npx", ["wp-env", "run", "cli", "wp", ...args], {
    cwd: repoRoot,
    encoding: "utf8",
    stdio: ["ignore", "pipe", "pipe"],
  }).trim();
}

function ensurePage({ title, slug }) {
  const existingId = runWp([
    "post",
    "list",
    "--post_type=page",
    `--name=${slug}`,
    "--post_status=publish,draft,pending,private",
    "--field=ID",
  ]);

  if (existingId) {
    runWp(["post", "update", existingId, `--post_title=${title}`, "--post_status=publish"]);
    return existingId;
  }

  return runWp([
    "post",
    "create",
    "--post_type=page",
    `--post_title=${title}`,
    `--post_name=${slug}`,
    "--post_status=publish",
    "--porcelain",
  ]);
}

function deletePostBySlug({ type, slug }) {
  const existingId = runWp([
    "post",
    "list",
    `--post_type=${type}`,
    `--name=${slug}`,
    "--post_status=publish,draft,pending,private",
    "--field=ID",
  ]);

  if (existingId) {
    runWp(["post", "delete", existingId, "--force"]);
  }
}

function runSetup() {
  runWp(["rewrite", "structure", "/%postname%/"]);
  runWp(["rewrite", "flush", "--hard"]);
  runWp(["theme", "activate", "industriesalon"]);

  deletePostBySlug({ type: "post", slug: "hello-world" });

  const homePageId = ensurePage({ title: "Home", slug: "home" });
  ensurePage({ title: "Fuehrungen", slug: "fuehrungen" });
  ensurePage({ title: "Ausstellungen", slug: "ausstellungen" });
  ensurePage({ title: "Veranstaltungen", slug: "veranstaltungen" });

  runWp(["option", "update", "show_on_front", "page"]);
  runWp(["option", "update", "page_on_front", homePageId]);
  runWp(["option", "update", "blogname", "Industriesalon E2E"]);
}

runSetup();
