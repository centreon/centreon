import { test as base, expect, type Locator } from '@playwright/test';

type Method = 'DELETE' | 'GET' | 'PATCH' | 'POST' | 'PUT';

interface MockApiOptions {
  /** Centreon endpoint as declared in the app, e.g. './api/latest/...'. */
  endpoint: string;
  method?: Method;
  response?: unknown;
  status?: number;
}

interface MockedEndpoint {
  /** JSON bodies of the requests received so far, in order. */
  bodies: Array<unknown>;
}

interface ComponentFixtures {
  /** Captures `target` in the light then the dark theme. */
  expectThemedScreenshot: (target: Locator, name: string) => Promise<void>;
  /**
   * Replaces cy.interceptAPIRequest. Register mocks before mount(), which
   * navigates to the gallery.
   */
  mockApi: (options: MockApiOptions) => Promise<MockedEndpoint>;
  /** Fails the test on React errors raised after mount(). */
  noGalleryErrors: undefined;
}

export const test = base.extend<ComponentFixtures>({
  expectThemedScreenshot: async ({ page }, use) => {
    await use(async (target, name) => {
      for (const colorScheme of ['light', 'dark'] as const) {
        await page.emulateMedia({ colorScheme });
        await expect(target).toHaveScreenshot(`${name}-${colorScheme}.png`);
      }
      await page.emulateMedia({ colorScheme: 'light' });
    });
  },

  mockApi: async ({ page }, use) => {
    await use(
      async ({ endpoint, method = 'GET', response = {}, status = 200 }) => {
        const mocked: MockedEndpoint = { bodies: [] };
        // App endpoints are relative to the page ('./api/...'): drop the dot so
        // the glob matches the resolved URL, whatever the query string.
        const path = endpoint.replace(/^\.\//, '');

        await page.route(
          (url) => url.pathname.endsWith(`/${path}`),
          async (route) => {
            if (route.request().method() !== method) {
              await route.fallback();

              return;
            }
            mocked.bodies.push(route.request().postDataJSON());
            await route.fulfill({ json: response, status });
          }
        );

        return mocked;
      }
    );
  },
  noGalleryErrors: [
    async ({ page }, use) => {
      await use(undefined);
      const errors = await page
        .evaluate(() => window.galleryErrors ?? [])
        .catch(() => []);
      expect(errors, 'React errors reported by the gallery').toEqual([]);
    },
    { auto: true }
  ]
});

export { expect };
