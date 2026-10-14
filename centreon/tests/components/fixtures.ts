import {
  test as base,
  expect,
  type Locator,
  type Page
} from '@playwright/test';

type Method = 'DELETE' | 'GET' | 'PATCH' | 'POST' | 'PUT';

interface MockApiOptions {
  /** Delay before answering, to observe loading states. */
  delay?: number;
  /** Centreon endpoint as declared in the app, e.g. './api/latest/...'. */
  endpoint: string;
  method?: Method;
  /** JSON body, or a function of the request URL (query-based APIs). */
  response?: unknown | ((url: URL) => unknown);
  status?: number;
}

interface MockedEndpoint {
  /** JSON bodies of the requests received so far, in order. */
  bodies: Array<unknown>;
  /** URLs of the requests received so far, in order. */
  urls: Array<URL>;
}

interface ComponentFixtures {
  /**
   * Captures `target` in the light then the dark theme. Pass the page only for
   * fixed-position components, whose mount root has no stable box.
   */
  expectThemedScreenshot: (
    target: Locator | Page,
    name: string
  ) => Promise<void>;
  /**
   * Replaces cy.interceptAPIRequest. Register mocks before mount(), which
   * navigates to the gallery.
   */
  mockApi: (options: MockApiOptions) => Promise<MockedEndpoint>;
  /** Fails the test on React errors raised after mount(). */
  noGalleryErrors: undefined;
}

// JSON whatever the content type (some calls send JSON as a form body).
const parseBody = (body: string | null): unknown => {
  if (body === null) {
    return null;
  }
  try {
    return JSON.parse(body);
  } catch {
    return body;
  }
};

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
      async ({
        delay = 0,
        endpoint,
        method = 'GET',
        response = {},
        status = 200
      }) => {
        const mocked: MockedEndpoint = { bodies: [], urls: [] };
        // App endpoints are relative to the page ('./api/...'): drop the dot so
        // the glob matches the resolved URL, whatever the query string.
        const path = endpoint.replace(/^\.\//, '').split('?')[0];

        await page.route(
          (url) => url.pathname.endsWith(`/${path}`),
          async (route) => {
            if (route.request().method() !== method) {
              await route.fallback();

              return;
            }
            const url = new URL(route.request().url());
            mocked.bodies.push(parseBody(route.request().postData()));
            mocked.urls.push(url);
            if (delay > 0) {
              await new Promise((resolve) => setTimeout(resolve, delay));
            }
            const json =
              typeof response === 'function' ? response(url) : response;
            await route.fulfill({ json, status });
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
