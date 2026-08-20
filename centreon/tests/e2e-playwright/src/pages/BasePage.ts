import { expect, type Page } from '@playwright/test';

import { centreonBasePath } from '../config/environment';

/**
 * Shared behaviour of every page object: how it is reached and how a test
 * asserts the browser actually sits on it.
 */
export abstract class BasePage {
  protected constructor(protected readonly page: Page) {}

  /** Path of the page relative to the Centreon base path, e.g. `/login`. */
  protected abstract get path(): string;

  get pathname(): string {
    return `${centreonBasePath}${this.path}`;
  }

  async goto(): Promise<void> {
    await this.page.goto(this.pathname);
  }

  async expectToBeDisplayed(): Promise<void> {
    await expect(this.page).toHaveURL((url) =>
      url.pathname.startsWith(this.pathname)
    );
  }
}
