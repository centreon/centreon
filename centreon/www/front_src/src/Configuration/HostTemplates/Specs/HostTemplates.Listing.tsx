import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelFilters,
  labelSearch
} from '../../ConfigurationBase/translatedLabels';
import {
  labelCreateHostTemplate,
  labelHostTemplates,
  labelHostTemplatesDescription,
  labelLocked,
  labelLockedElements,
  labelWelcomeToHostTemplates
} from '../translatedLabels';
import initialize from './initialize';

export default () => {
  describe('Listing: ', () => {
    it('reads the listing from API Platform, without the locked templates by default', async () => {
      initialize();

      const { request } = await waitForRequest('@getAllHostTemplates');

      expect(request.url.pathname).toContain(
        '/api/configuration/host_templates'
      );
      expect(request.url.pathname).not.toContain('/api/latest');
      expect(request.url.searchParams.get('page')).toBe('1');
      // As legacy: the locked templates stay hidden until asked for.
      expect(request.url.searchParams.get('is_locked')).toBe('false');

      expect(await screen.findByText(labelHostTemplates)).toBeVisible();
      expect(screen.getByText(labelHostTemplatesDescription)).toBeVisible();
    });

    it('displays the templates returned by the API', async () => {
      initialize();

      await waitForRequest('@getAllHostTemplates');

      expect(await screen.findByText('Generic active host')).toBeVisible();
      // The parent templates, each a link to the template.
      expect(screen.getByTestId('host-template-link_6')).toHaveTextContent(
        'generic-passive-host'
      );
      // The linked service templates count, 0 when the API leaves it out.
      const rows = screen.getAllByRole('row');

      expect(
        within(
          rows.find((row) =>
            row.textContent?.includes('Generic active host')
          ) as HTMLElement
        ).getByText('2')
      ).toBeVisible();
      expect(
        within(
          rows.find((row) =>
            row.textContent?.startsWith('generic-passive-host')
          ) as HTMLElement
        ).getByText('0')
      ).toBeVisible();
    });

    it('marks a locked template with a lock', async () => {
      initialize();

      await waitForRequest('@getAllHostTemplates');

      expect(
        await screen.findByTestId('host-template-locked_8')
      ).toHaveAccessibleName(labelLocked);
      expect(
        screen.queryByTestId('host-template-locked_5')
      ).not.toBeInTheDocument();
    });

    it('asks for the locked templates too once the filter is checked', async () => {
      initialize();

      await waitForRequest('@getAllHostTemplates');

      await userEvent.click(await screen.findByTestId(labelFilters));
      const panel = await screen.findByTestId('advanced-filters');

      await waitFor(() => expect(panel).toBeVisible());

      await userEvent.click(within(panel).getByTestId(labelLockedElements));
      await userEvent.click(screen.getByTestId(labelSearch));

      const { request } = await waitForRequest('@getAllHostTemplates');

      expect(request.url.searchParams.has('is_locked')).toBe(false);
    });

    it('displays the welcome page when no template exists', async () => {
      initialize({ isEmpty: true });

      await waitForRequest('@getAllHostTemplates');

      const emptyState = await screen.findByTestId('data-table-empty-state');

      expect(
        within(emptyState).getByRole('heading', {
          name: labelWelcomeToHostTemplates
        })
      ).toBeVisible();
      expect(
        within(emptyState).getByRole('button', { name: 'create' })
      ).toHaveTextContent(new RegExp(`^${labelCreateHostTemplate}$`));
    });
  });
};
