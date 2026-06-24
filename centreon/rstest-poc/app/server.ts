import { rest } from 'msw';
import { setupServer } from 'msw/node';

/**
 * MSW node server — the Rstest equivalent of Cypress' `cy.interceptAPIRequest`
 * / `cy.waitForRequest` (which are themselves MSW-based via
 * cypress-msw-interceptor). `interceptApiRequest` registers a handler and
 * captures the outgoing request so a test can assert its payload.
 */
export const server = setupServer();

export interface CapturedRequest {
  body: unknown;
  method: string;
  searchParams: URLSearchParams;
  url: string;
}

const captured = new Map<string, CapturedRequest>();
const history = new Map<string, Array<CapturedRequest>>();
const waiters = new Map<string, (request: CapturedRequest) => void>();

export const resetInterceptions = (): void => {
  captured.clear();
  history.clear();
  waiters.clear();
};

/** All requests captured for an alias, in order (handles repeated calls). */
export const getRequests = (alias: string): Array<CapturedRequest> =>
  history.get(alias) ?? [];

interface QueryMatch {
  name: string;
  // When omitted, the handler matches as long as the param is present.
  value?: string;
}

interface InterceptOptions {
  alias: string;
  method: 'delete' | 'get' | 'patch' | 'post' | 'put';
  path: string;
  // Only respond when this query param matches (mirrors cy.interceptAPIRequest).
  query?: QueryMatch;
  response?: unknown;
  statusCode?: number;
}

export const interceptApiRequest = ({
  alias,
  method,
  path,
  query,
  response = {},
  statusCode = 200
}: InterceptOptions): void => {
  // Match by URL pathname suffix (ignore the query string in the path), like
  // the Cypress glob does; query discrimination is handled in the resolver.
  const pathname = path.replace(/^\.?\/?/, '/').split('?')[0];
  const matcher = `*${pathname}`;

  server.use(
    rest[method](matcher, async (req, res, ctx) => {
      if (query) {
        const actual = req.url.searchParams.get(query.name);
        const matches =
          query.value === undefined ? actual !== null : actual === query.value;
        if (!matches) {
          return req.passthrough();
        }
      }

      let body: unknown;
      try {
        body = await req.json();
      } catch {
        body = undefined;
      }
      const request: CapturedRequest = {
        body,
        method: method.toUpperCase(),
        searchParams: req.url.searchParams,
        url: req.url.toString()
      };
      captured.set(alias, request);
      history.set(alias, [...(history.get(alias) ?? []), request]);
      waiters.get(alias)?.(request);

      return res(ctx.status(statusCode), ctx.json(response));
    })
  );
};

/**
 * Assert the captured request's query params (mirrors
 * cy.waitForRequestAndVerifyQueries). Values are JSON-decoded when possible.
 */
export const verifyRequestQueries = async (
  alias: string,
  queries: Array<{ key: string; value: unknown }>
): Promise<void> => {
  const { searchParams } = await waitForRequest(alias);
  queries.forEach(({ key, value }) => {
    const raw = searchParams.get(key);
    let decoded: unknown = raw;
    try {
      decoded = raw === null ? null : JSON.parse(raw);
    } catch {
      decoded = raw;
    }
    if (JSON.stringify(decoded) !== JSON.stringify(value)) {
      throw new Error(
        `query "${key}" mismatch: expected ${JSON.stringify(
          value
        )}, got ${JSON.stringify(decoded)}`
      );
    }
  });
};

export const waitForRequest = (alias: string): Promise<CapturedRequest> => {
  const existing = captured.get(alias);
  if (existing) {
    return Promise.resolve(existing);
  }
  return new Promise((resolve) => waiters.set(alias, resolve));
};
