import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://127.0.0.1:8000' });

test.describe('entry modal close methods', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');
        await page.evaluate(() => localStorage.clear());
        await page.reload();
        await page.waitForLoadState('networkidle');
    });

    test('close button (×) closes the modal', async ({ page }) => {
        // Open modal
        await page.locator('.quick-add-bar .primary-button').first().click();
        await page.locator('.quick-add-option').first().click();
        const dialog = page.locator('dialog.entry-dialog[open]');
        await expect(dialog).toBeVisible({ timeout: 3000 });

        // Close via the × button
        await dialog.locator('.entry-modal__close').click();
        await expect(dialog).toBeHidden({ timeout: 3000 });
    });

    test('Escape key closes the modal', async ({ page }) => {
         // Open modal
        await page.locator('.quick-add-bar .primary-button').first().click();
        await page.locator('.quick-add-option').first().click();
        const dialog = page.locator('dialog.entry-dialog[open]');
        await expect(dialog).toBeVisible({ timeout: 3000 });

        // Press Escape
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden({ timeout: 3000 });
    });
});
