import { Method } from '@centreon/ui';

import { HttpResponse, http, type JsonBodyType, delay as wait } from 'msw';

import { worker } from './mswWorker';

// The request as the tests read it: the URL parsed, the body decoded.
export interface RecordedRequest {
  body: unknown;
  method: string;
  url: URL;
}

interface Call {
  // Whether the mock has sent its response yet.
  answered: boolean;
  request: RecordedRequest;
}

interface Query {
  name: string;
  value: string;
}

export interface InterceptAPIRequestProps {
  alias: string;
  delay?: number;
  method: Method;
  path: string;
  query?: Query;
  response?: unknown;
  statusCode?: number;
}

type Resolver = (props: {
  request: RecordedRequest;
}) => Response | Promise<Response>;

const waitTimeout = 10_000;

// Every request an alias received, in order, and how far the tests have read.
const calls = new Map<string, Array<Call>>();
const cursors = new Map<string, number>();
// Bumped at the end of each test, so a response still pending then is not
// logged as a request of the next test.
let generation = 0;
// The pending waits: `check` on every new request, `cancel` at the end of
// the test.
const listeners = new Set<{ cancel: () => void; check: () => void }>();

const notify = (): void => {
  listeners.forEach(({ check }) => {
    check();
  });
};

// A wait still pending when its test ends is cancelled, so it can neither
// consume a request of the next test nor fail it later.
export const resetRequests = (): void => {
  listeners.forEach(({ cancel }) => {
    cancel();
  });
  listeners.clear();
  calls.clear();
  cursors.clear();
  generation += 1;
};

const normalizeAlias = (alias: string): string => alias.replace(/^@/, '');

// The Cypress globs (`**/path?**`, `./path`) as MSW 2 paths: MSW ignores the
// query string when matching, and only knows a single leading wildcard.
const toMswPath = (path: string): string =>
  path.replace(/^\.\//, '*/').replace(/^\*\*/, '*').replace(/\?.*$/, '');

const decodeBody = async (request: Request): Promise<unknown> => {
  const text = await request.clone().text();

  if (!text) {
    return undefined;
  }

  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
};

// Logged on arrival, as cypress-msw-interceptor did, so `getRequestCalls`
// also counts the requests a delayed mock has not answered yet. A request
// whose test ended while it was being read is not logged at all.
const receive = (
  alias: string,
  request: RecordedRequest,
  receivedIn: number
): (() => void) => {
  if (receivedIn !== generation) {
    return (): void => undefined;
  }

  const key = normalizeAlias(alias);
  const call: Call = { answered: false, request };

  calls.set(key, [...(calls.get(key) ?? []), call]);

  return (): void => {
    if (receivedIn !== generation) {
      return;
    }

    call.answered = true;
    notify();
  };
};

const toRecordedRequest = async (
  request: Request
): Promise<RecordedRequest> => ({
  body: await decodeBody(request),
  method: request.method,
  url: new URL(request.url)
});

const methods = {
  [Method.DELETE]: http.delete,
  [Method.GET]: http.get,
  [Method.PATCH]: http.patch,
  [Method.POST]: http.post,
  [Method.PUT]: http.put
};

// The rstest counterpart of `cy.interceptRequest`: the resolver builds the
// response itself, and every request it answers is recorded under the alias.
export const interceptRequest = (
  method: Method,
  path: string,
  resolver: Resolver,
  alias: string
): void => {
  worker.use(
    methods[method](toMswPath(path), async ({ request }) => {
      const receivedIn = generation;
      const recorded = await toRecordedRequest(request);
      const answer = receive(alias, recorded, receivedIn);
      const response = await resolver({ request: recorded });

      answer();

      return response;
    })
  );
};

// The rstest counterpart of `cy.interceptAPIRequest`. Unlike the Cypress
// command, the response is not delayed unless a test asks for it.
export const interceptAPIRequest = ({
  alias,
  delay = 0,
  method,
  path,
  query,
  response,
  statusCode = 200
}: InterceptAPIRequestProps): void => {
  worker.use(
    methods[method](toMswPath(path), async ({ request }) => {
      const url = new URL(request.url);
      const queryValue = query ? url.searchParams.get(query.name) : null;

      // Left to the next handler, as the Cypress command did.
      if (query ? queryValue !== query.value : false) {
        return undefined;
      }

      const receivedIn = generation;
      const answer = receive(
        alias,
        await toRecordedRequest(request),
        receivedIn
      );

      if (delay > 0) {
        await wait(delay);
      }

      answer();

      return HttpResponse.json(response as JsonBodyType, {
        status: statusCode
      });
    })
  );
};

// Resolves once the alias has answered a request the test has not waited for
// yet, with the latest one — as `cy.waitForRequest` returned the last call.
// As with Cypress, it resolves when the response is sent, before the page has
// applied it: what the page does with it is awaited with `findBy*`/`waitFor`.
export const waitForRequest = (
  alias: string,
  { timeout = waitTimeout }: { timeout?: number } = {}
): Promise<Call> => {
  const key = normalizeAlias(alias);

  return new Promise((resolve, reject) => {
    const check = (): boolean => {
      const received = calls.get(key) ?? [];
      const cursor = cursors.get(key) ?? 0;
      const lastAnswered = received.findLastIndex(
        (call, index) => index >= cursor && call.answered
      );

      if (lastAnswered === -1) {
        return false;
      }

      cursors.set(key, lastAnswered + 1);
      resolve(received[lastAnswered]);

      return true;
    };

    if (check()) {
      return;
    }

    const timer = setTimeout(() => {
      listeners.delete(listener);
      reject(
        new Error(
          `No new request answered by "@${key}" within ${timeout} ms (${calls.get(key)?.length ?? 0} so far)`
        )
      );
    }, timeout);

    const listener = {
      cancel: (): void => {
        clearTimeout(timer);
        reject(new Error(`The test ended while waiting for "@${key}"`));
      },
      check: (): void => {
        if (check()) {
          clearTimeout(timer);
          listeners.delete(listener);
        }
      }
    };

    listeners.add(listener);
  });
};

// Every request the alias received, answered or not, waited for or not.
export const getRequestCalls = (alias: string): Array<Call> =>
  calls.get(normalizeAlias(alias)) ?? [];

export { Method };
