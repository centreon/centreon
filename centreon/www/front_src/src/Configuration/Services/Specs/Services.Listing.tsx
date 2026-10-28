import { waitForRequest } from '@test-setup/api';
import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelCreateService,
  labelServices,
  labelServicesDescription,
  labelWelcomeToServices
} from '../translatedLabels';
import initialize from './initialize';

export default () => {
  describe('Listing: ', () => {
    it('reads the listing from API Platform, not the Core route', async () => {
      initialize();

      // The intercept glob matches the Core base too, so assert the URL.
      const { request } = await waitForRequest('@getAllServices');

      expect(request.url.pathname).toContain('/api/configuration/services');
      expect(request.url.pathname).not.toContain('/api/latest');
      expect(request.url.searchParams.get('page')).toBe('1');

      expect(await screen.findByText(labelServices)).toBeVisible();
      expect(screen.getByText(labelServicesDescription)).toBeVisible();
    });

    it('requests 10 services per page until the platform setting is known', async () => {
      initialize();

      const { request } = await waitForRequest('@getAllServices');

      expect(request.url.searchParams.get('itemsPerPage')).toBe('10');
    });

    it('requests as many services per page as the platform "Limit per page" setting', async () => {
      initialize({ limitPerPage: 30 });

      const { request } = await waitForRequest('@getAllServices');

      expect(request.url.searchParams.get('itemsPerPage')).toBe('30');
    });

    it('keeps the page size the user picks over the platform setting', async () => {
      initialize({ limitPerPage: 30 });

      await waitForRequest('@getAllServices');
      expect(await screen.findByText('cpu')).toBeVisible();

      // biome-ignore lint/style/noNonNullAssertion: the pagination is rendered
      await userEvent.click(document.getElementById('Rows per page')!);
      await userEvent.click(await screen.findByRole('option', { name: '20' }));

      const { request } = await waitForRequest('@getAllServices');

      expect(request.url.searchParams.get('itemsPerPage')).toBe('20');
    });

    it('sorts on the host name first, as the legacy page did', async () => {
      initialize();

      const { request } = await waitForRequest('@getAllServices');

      expect(request.url.searchParams.get('sort[host.name]')).toBe('asc');
      expect(request.url.searchParams.get('sort[name]')).toBeNull();
    });

    it('displays the services returned by the API', async () => {
      initialize();

      await waitForRequest('@getAllServices');

      expect(await screen.findByText('cpu')).toBeVisible();
      expect(screen.getByText('memory')).toBeVisible();
      expect(screen.getByText('disk')).toBeVisible();
    });

    it('displays the welcome page when no service exists', async () => {
      initialize({ isEmpty: true });

      await waitForRequest('@getAllServices');

      expect(await screen.findByText(labelWelcomeToServices)).toBeVisible();

      const emptyState = screen.getByTestId('data-table-empty-state');

      const create = within(emptyState).getByRole('button', { name: 'create' });

      expect(create).toHaveTextContent(new RegExp(`^${labelCreateService}$`));
      expect(create).toBeEnabled();
    });
  });
};
