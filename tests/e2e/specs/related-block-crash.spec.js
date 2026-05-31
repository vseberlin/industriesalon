const { test, expect } = require("@playwright/test");

async function login(page) {
  await page.goto("/wp-login.php");
  await page.getByLabel(/username or email address/i).fill("admin");
  await page.locator("#user_pass").fill("password");
  await page.getByRole("button", { name: /log in/i }).click();
}

test("related cards block does not crash the editor", async ({ page }) => {
  const consoleMessages = [];
  const pageErrors = [];
  const requestFailures = [];

  page.on("console", (msg) => {
    consoleMessages.push(`[${msg.type()}] ${msg.text()}`);
  });
  page.on("pageerror", (error) => {
    pageErrors.push(error.message);
  });
  page.on("requestfailed", (request) => {
    requestFailures.push(`${request.method()} ${request.url()} :: ${request.failure()?.errorText || "failed"}`);
  });

  await login(page);
  await page.goto("/wp-admin/post-new.php?post_type=page");
  await page.locator(".editor-post-title__input").waitFor();

  const welcomeDialog = page.getByRole("dialog", { name: /welcome to the editor/i });
  if (await welcomeDialog.isVisible().catch(() => false)) {
    await welcomeDialog.getByRole("button", { name: /close/i }).click();
  }

  await page.evaluate(() => {
    const createBlock = window.wp?.blocks?.createBlock;
    const insertBlocks = window.wp?.data?.dispatch("core/block-editor")?.insertBlocks;

    if (!createBlock || !insertBlocks) {
      throw new Error("Block editor APIs are unavailable.");
    }

    insertBlocks([
      createBlock("iss/related-cards", {
        postType: "page",
        perPage: 3,
        source: "current",
        layoutVariant: "grid",
        columns: 3,
        showImage: true,
        showExcerpt: true,
      }),
      createBlock("iss/related-content", {
        title: "Verwandte Inhalte",
        postType: "page",
        perPage: 3,
        source: "current",
        layoutVariant: "grid",
        columns: 3,
        showImage: true,
        showExcerpt: true,
      }),
    ]);
  });

  await page.waitForTimeout(4000);

  const blockNames = await page.evaluate(() => {
    return (window.wp?.data?.select("core/block-editor")?.getBlocks?.() || []).map((block) => block.name);
  });

  expect(blockNames, `page errors:\n${pageErrors.join("\n")}\n\nconsole:\n${consoleMessages.join("\n")}\n\nfailed requests:\n${requestFailures.join("\n")}`).toEqual([
    "iss/related-cards",
    "iss/related-content",
  ]);
  expect(pageErrors, `page errors:\n${pageErrors.join("\n")}\n\nconsole:\n${consoleMessages.join("\n")}\n\nfailed requests:\n${requestFailures.join("\n")}`).toEqual([]);
});
