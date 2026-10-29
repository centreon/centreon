import { defaultLimitPerPageAtom } from '@centreon/ui-context';

import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelCreateHost,
  labelHosts,
  labelHostsDescription,
  labelWelcomeToHosts
} from '../translatedLabels';
import initialize from './initialize';

// The page size selector, looked up in the pagination rather than as the
// page's only combobox.
const findPageSizeSelector = async (): Promise<HTMLElement> =>
  within(await screen.findByTestId('Listing Pagination')).getByRole('combobox');

const getSentPageSizes = (): Array<string | null> =>
  getRequestCalls('@getAllHosts').map(({ request }) =>
    request.url.searchParams.get('itemsPerPage')
  );

export default () => {
  describe('Listing: ', () => {
    it('reads the listing from API Platform, not the legacy route', async () => {
      initialize();

      // The intercept glob matches the legacy base too, so assert the URL.
      const { request } = await waitForRequest('@getAllHosts');

      expect(request.url.pathname).toContain('/api/configuration/hosts');
      expect(request.url.pathname).not.toContain('/api/latest');
      expect(request.url.searchParams.get('page')).toBe('1');
      expect(request.url.searchParams.get('itemsPerPage')).toBe('10');

      expect(await screen.findByText(labelHosts)).toBeVisible();
    });

    it('displays the subtitle next to the page title', async () => {
      initialize();

      expect(await screen.findByText(labelHostsDescription)).toBeVisible();
      expect(screen.getByLabelText('page header title')).toHaveTextContent(
        labelHosts
      );
    });

    it('displays the hosts returned by the API', async () => {
      initialize();

      await waitForRequest('@getAllHosts');

      expect(await screen.findByText('host 0')).toBeVisible();
      expect(screen.getByText('10.0.0.0')).toBeVisible();
      expect(screen.getAllByText('Central')[0]).toBeVisible();
    });

    it('decodes a host whose optional fields are absent from the response', async () => {
      initialize();

      await waitForRequest('@getAllHosts');

      expect(await screen.findByText('host 1')).toBeVisible();
      expect(screen.getByText('10.0.0.1')).toBeVisible();
      expect(screen.queryByText('alias for host 1')).not.toBeInTheDocument();
    });

    // 50, not 30: 30 is also the listing's own fallback, which would pass
    // these tests without the platform value.
    it('opens with the platform default page size', async () => {
      initialize({ defaultLimitPerPage: 50 });

      const { request } = await waitForRequest('@getAllHosts');

      expect(request.url.searchParams.get('itemsPerPage')).toBe('50');
    });

    it('keeps the page size the user picked over the platform default', async () => {
      initialize({ defaultLimitPerPage: 50, userLimit: 20 });

      const { request } = await waitForRequest('@getAllHosts');

      expect(request.url.searchParams.get('itemsPerPage')).toBe('20');
    });

    it('sends the page size the user picks instead of the platform default', async () => {
      const { store } = initialize({ defaultLimitPerPage: 50 });

      await waitForRequest('@getAllHosts');

      const pageSize = await findPageSizeSelector();

      expect(pageSize).toHaveTextContent(/^50$/);

      await userEvent.click(pageSize);
      await userEvent.click(await screen.findByRole('option', { name: '20' }));

      const { request } = await waitForRequest('@getAllHosts');

      expect(request.url.searchParams.get('itemsPerPage')).toBe('20');

      // The user's choice stays when the platform default changes.
      act(() => {
        store.set(defaultLimitPerPageAtom, 40);
      });

      await waitFor(async () => {
        expect(await findPageSizeSelector()).toHaveTextContent(/^20$/);
      });

      // A request the platform change would have sent goes before the one
      // this pick sends, so once the latter is answered no other can come.
      await userEvent.click(await findPageSizeSelector());
      await userEvent.click(await screen.findByRole('option', { name: '60' }));
      await waitForRequest('@getAllHosts');

      expect(getSentPageSizes()).toEqual(['50', '20', '60']);
    });

    it('sends no listing request until the platform default page size is known', async () => {
      const { store } = initialize({ defaultLimitPerPage: null });

      expect(await screen.findByText(labelHosts)).toBeVisible();
      // Waiting is loading, not an empty listing.
      await waitFor(() => {
        expect(document.querySelector('.MuiSkeleton-root')).toBeVisible();
      });
      expect(screen.queryByText(labelWelcomeToHosts)).not.toBeInTheDocument();
      expect(getRequestCalls('@getAllHosts')).toHaveLength(0);

      act(() => {
        store.set(defaultLimitPerPageAtom, 50);
      });

      const { request } = await waitForRequest('@getAllHosts');

      expect(request.url.searchParams.get('itemsPerPage')).toBe('50');

      // Once the rows are shown, the listing has settled on this request.
      expect(await screen.findByText('host 0')).toBeVisible();
      expect(
        document.querySelector('.MuiSkeleton-root')
      ).not.toBeInTheDocument();
      expect(getSentPageSizes()).toEqual(['50']);
    });

    it('displays the welcome page when no host exists', async () => {
      initialize({ isEmpty: true });

      await waitForRequest('@getAllHosts');

      expect(await screen.findByText(labelWelcomeToHosts)).toBeVisible();

      // What the screenshot showed: the welcome title and the way to create
      // the first host.
      const emptyState = screen.getByTestId('data-table-empty-state');

      expect(
        within(emptyState).getByRole('heading', { name: labelWelcomeToHosts })
      ).toBeVisible();

      const create = within(emptyState).getByRole('button', { name: 'create' });

      expect(create).toHaveTextContent(new RegExp(`^${labelCreateHost}$`));
      expect(create).toBeEnabled();
    });
  });
};
