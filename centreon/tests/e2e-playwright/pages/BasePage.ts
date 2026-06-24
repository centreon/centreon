import type { Page } from '@playwright/test';

/**
 * Base class shared by every Page Object.
 *
 * It holds the Playwright `Page` handle and exposes a couple of low-level
 * helpers so that concrete pages only describe locators and user intentions,
 * never the plumbing.
 */
export abstract class BasePage {
  protected readonly page: Page;

  constructor(page: Page) {
    this.page = page;
  }

  /**
   * Navigate to a path relative to the configured `baseURL`.
   * Passing an empty string lands on the Centreon root.
   */
  async goto(path = ''): Promise<void> {
    await this.page.goto(path, { waitUntil: 'domcontentloaded' });
  }

  /** Current browser URL, handy for assertions in the tests. */
  url(): string {
    return this.page.url();
  }
}
