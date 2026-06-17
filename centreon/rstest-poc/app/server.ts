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
  url: string;
}

const captured = new Map<string, CapturedRequest>();
const waiters = new Map<string, (request: CapturedRequest) => void>();

export const resetInterceptions = (): void => {
  captured.clear();
  waiters.clear();
};

interface InterceptOptions {
  alias: string;
  method: 'delete' | 'get' | 'patch' | 'post' | 'put';
  path: string;
  response?: unknown;
  statusCode?: number;
}

export const interceptApiRequest = ({
  alias,
  method,
  path,
  response = {},
  statusCode = 200
}: InterceptOptions): void => {
  // Match by URL suffix (the endpoint tail), like the Cypress glob does.
  const matcher = `*${path.replace(/^\.?\/?/, '/')}`;

  server.use(
    rest[method](matcher, async (req, res, ctx) => {
      let body: unknown;
      try {
        body = await req.json();
      } catch {
        body = undefined;
      }
      const request: CapturedRequest = {
        body,
        method: method.toUpperCase(),
        url: req.url.toString()
      };
      captured.set(alias, request);
      waiters.get(alias)?.(request);

      return res(ctx.status(statusCode), ctx.json(response));
    })
  );
};

export const waitForRequest = (alias: string): Promise<CapturedRequest> => {
  const existing = captured.get(alias);
  if (existing) {
    return Promise.resolve(existing);
  }
  return new Promise((resolve) => waiters.set(alias, resolve));
};
