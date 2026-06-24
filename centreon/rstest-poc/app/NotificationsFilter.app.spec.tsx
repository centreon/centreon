import { describe, expect, it } from '@rstest/core';
import userEvent from '@testing-library/user-event';

import TestQueryProvider from '../../packages/ui/src/api/TestQueryProvider';
import Filter from '../../www/front_src/src/CloudNotificationsConfiguration/Filter';
import { buildNotificationsEndpoint } from '../../www/front_src/src/CloudNotificationsConfiguration/Listing/api/endpoints';
import {
  defaultQueryParams,
  getListingResponse
} from '../../www/front_src/src/CloudNotificationsConfiguration/Listing/Tests/testUtils';
import useLoadingNotifications from '../../www/front_src/src/CloudNotificationsConfiguration/Listing/useLoadNotifications';
import { labelSearch } from '../../www/front_src/src/CloudNotificationsConfiguration/translatedLabels';
import { renderApp, screen, waitFor } from './render';
import { getRequests, interceptApiRequest } from './server';

/**
 * Phase 0b port of CloudNotificationsConfiguration/Filter/NotificationsFilter.cypress.spec.tsx
 * — the common "list + debounced search" shape: a GET on mount, a second GET
 * when typing, and an assertion on the outgoing search query param.
 */
const FilterWithQueryProvider = (): JSX.Element => {
  useLoadingNotifications();

  return (
    <TestQueryProvider>
      <Filter />
    </TestQueryProvider>
  );
};

describe('NotificationsFilter (Rstest app POC)', () => {
  it('executes a listing request with the search param when a value is typed', async () => {
    // The default listing already sends an (empty) `search`, so we capture all
    // notification GETs and assert that typing produces one carrying the search.
    interceptApiRequest({
      alias: 'notifications',
      method: 'get',
      path: buildNotificationsEndpoint(defaultQueryParams),
      response: getListingResponse({})
    });

    renderApp(<FilterWithQueryProvider />);

    const input = await screen.findByPlaceholderText(labelSearch);
    await userEvent.clear(input);
    await userEvent.type(input, 'foobar');

    await waitFor(() => {
      const searches = getRequests('notifications').map((request) => {
        const raw = request.searchParams.get('search');
        return raw ? JSON.parse(raw) : null;
      });
      expect(searches).toContainEqual({ $or: [{ name: { $rg: 'foobar' } }] });
    });
  });
});
