import { waitFor } from '@testing-library/react';

import {
  getRequestCalls,
  interceptAPIRequest,
  Method,
  resetRequests,
  waitForRequest
} from './api';

// Every component test relies on these helpers: a bug here would make tests
// pass without testing anything, so their behaviour is pinned here.
describe('test-setup/api', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('takes the Cypress globs as they are, query string included', async () => {
    interceptAPIRequest({
      alias: 'listing',
      method: Method.GET,
      path: '**/configuration/things?**',
      response: { member: [] }
    });
    interceptAPIRequest({
      alias: 'relative',
      method: Method.GET,
      path: './api/other',
      response: { ok: true }
    });

    const listing = await fetch('/centreon/configuration/things?page=1');
    const relative = await fetch('/centreon/api/other');

    expect(await listing.json()).toEqual({ member: [] });
    expect(await relative.json()).toEqual({ ok: true });

    const { request } = await waitForRequest('@listing');

    expect(request.url.searchParams.get('page')).toBe('1');
    expect(getRequestCalls('relative')).toHaveLength(1);
  });

  it('matches a path exactly, not the paths that start with it', async () => {
    interceptAPIRequest({
      alias: 'list',
      method: Method.GET,
      path: '**/things?**',
      response: { which: 'list' }
    });
    interceptAPIRequest({
      alias: 'one',
      method: Method.GET,
      path: '**/things/1',
      response: { which: 'one' }
    });

    expect(await (await fetch('/things/1')).json()).toEqual({ which: 'one' });
    expect(getRequestCalls('list')).toHaveLength(0);
  });

  it('leaves a request to the next mock when its query does not match', async () => {
    interceptAPIRequest({
      alias: 'any',
      method: Method.GET,
      path: '**/search',
      response: { which: 'any' }
    });
    interceptAPIRequest({
      alias: 'filtered',
      method: Method.GET,
      path: '**/search',
      query: { name: 'q', value: 'linux' },
      response: { which: 'filtered' }
    });

    expect(await (await fetch('/search?q=linux')).json()).toEqual({
      which: 'filtered'
    });
    expect(await (await fetch('/search?q=windows')).json()).toEqual({
      which: 'any'
    });
    expect(getRequestCalls('filtered')).toHaveLength(1);
    expect(getRequestCalls('any')).toHaveLength(1);
  });

  it('answers with the status code given', async () => {
    interceptAPIRequest({
      alias: 'missing',
      method: Method.GET,
      path: '**/missing',
      response: { message: 'Not found' },
      statusCode: 404
    });

    const response = await fetch('/missing');

    expect(response.status).toBe(404);
    expect(await response.json()).toEqual({ message: 'Not found' });
  });

  it('decodes JSON bodies, keeps other bodies as text, and leaves no body undefined', async () => {
    interceptAPIRequest({
      alias: 'write',
      method: Method.POST,
      path: '**/write',
      response: {}
    });

    await fetch('/write', { body: JSON.stringify({ a: 1 }), method: 'POST' });
    await fetch('/write', { body: 'plain', method: 'POST' });
    await fetch('/write', { method: 'POST' });

    expect(
      getRequestCalls('@write').map(({ request }) => request.body)
    ).toEqual([{ a: 1 }, 'plain', undefined]);
  });

  it('resolves a wait with the latest request answered since the previous wait', async () => {
    interceptAPIRequest({
      alias: 'item',
      method: Method.GET,
      path: '**/item',
      response: {}
    });

    await fetch('/item?n=1');
    await fetch('/item?n=2');

    const { request } = await waitForRequest('@item');

    expect(request.url.searchParams.get('n')).toBe('2');

    const pending = waitForRequest('@item');

    await fetch('/item?n=3');

    expect((await pending).request.url.searchParams.get('n')).toBe('3');
  });

  it('does not resolve a second wait on a request sent once', async () => {
    interceptAPIRequest({
      alias: 'once',
      method: Method.GET,
      path: '**/once',
      response: {}
    });

    await fetch('/once');
    await waitForRequest('@once');

    await expect(waitForRequest('@once', { timeout: 200 })).rejects.toThrow(
      'No new request answered by "@once" within 200 ms (1 so far)'
    );
  });

  it('counts a request as soon as it arrives, and waits for its answer', async () => {
    interceptAPIRequest({
      alias: 'slow',
      delay: 300,
      method: Method.GET,
      path: '**/slow',
      response: {}
    });

    const response = fetch('/slow');

    await waitFor(() => expect(getRequestCalls('slow')).toHaveLength(1));
    expect(getRequestCalls('slow')[0].answered).toBe(false);

    await waitForRequest('@slow');

    expect(getRequestCalls('slow')[0].answered).toBe(true);
    await response;
  });

  it('cancels the waits still pending when a test ends', async () => {
    const pending = waitForRequest('@never');

    resetRequests();

    await expect(pending).rejects.toThrow(
      'The test ended while waiting for "@never"'
    );
  });
});
