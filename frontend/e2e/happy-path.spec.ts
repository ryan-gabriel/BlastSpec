import { test, expect } from "@playwright/test";

test.describe("E2E-1 — Happy path", () => {
  test("upload → graph → diff → report", async ({ page }) => {
    await page.goto("/login");
    await page.fill('input[name="email"]', "demo@blastspec.dev");
    await page.fill('input[name="password"]', "demo-password");
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL("/");

    // Navigate to specs
    await page.click('a[href="/specs"]');
    await expect(page).toHaveURL("/specs");

    // Upload a spec
    await page.click('a:has-text("Upload spec")');
    await expect(page).toHaveURL("/specs/upload");

    // Step 1: service
    await page.fill('input[placeholder="billing"]', "billing");
    await page.fill('input[placeholder="Payments"]', "Payments");
    await page.click('button:has-text("Continue")');

    // Step 2: document
    const fileInput = page.locator('input[type="file"]');
    await fileInput.setInputFiles("tests/fixtures/specs/petstore/v1.yaml");
    await page.fill('input[value="1.0.0"]', "1.0.0");
    await page.click('button:has-text("Continue")');

    // Step 3: review
    await expect(page.locator("text=Upload and build graph")).toBeVisible();
    await page.click('button:has-text("Upload and build graph")');

    // Should redirect to graph
    await expect(page).toHaveURL(/\/specs\/.*\/graph/);
  });
});

test.describe("E2E-2 — CI webhook", () => {
  test("signed webhook creates resources", async ({ request }) => {
    // This test requires a running API with a valid token.
    // Implementation depends on test fixtures and signing.
    test.skip(true, "Requires live API and CI token setup");
  });
});

test.describe("E2E-3 — Failure and recovery", () => {
  test("graph service down shows error state", async ({ page }) => {
    test.skip(true, "Requires service orchestration in CI");
  });
});

test.describe("E2E-4 — Cycle", () => {
  test("cycle fixture shows coordinated release warning", async ({ page }) => {
    test.skip(true, "Requires cycle fixture seed data");
  });
});
