import { waitForRequest } from '@test-setup/api';
import { screen, within } from '@testing-library/react';

import {
  labelCreateHost,
  labelHosts,
  labelHostsDescription,
  labelWelcomeToHosts
} from '../translatedLabels';
import initialize from './initialize';

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
