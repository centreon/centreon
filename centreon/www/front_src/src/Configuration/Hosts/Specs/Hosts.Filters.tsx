import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelClear,
  labelFilters,
  labelSearch
} from '../../ConfigurationBase/translatedLabels';
import {
  labelHostGroup,
  labelHostTemplate,
  labelMonitoringServer,
  labelName,
  labelStatus
} from '../translatedLabels';
import initialize from './initialize';

const openAdvancedFilters = async (): Promise<HTMLElement> => {
  await userEvent.click(await screen.findByTestId(labelFilters));

  const panel = await screen.findByTestId('advanced-filters');

  await waitFor(() => expect(panel).toBeVisible());

  return panel;
};

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper: a template name also appears in the Templates column
// of the listing behind it.
const select = async (
  label: string,
  option: string,
  alias: string
): Promise<void> => {
  await userEvent.click(screen.getByTestId(label));
  await waitForRequest(alias);
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

const search = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId(labelSearch));
};

const initializeWithAdvancedFilters = async (): Promise<HTMLElement> => {
  initialize();

  await waitForRequest('@getAllHosts');

  return openAdvancedFilters();
};

export default () => {
  describe('Filters: ', () => {
    it('offers the five filters of the listing', async () => {
      const panel = await initializeWithAdvancedFilters();

      [
        labelName,
        labelHostGroup,
        labelHostTemplate,
        labelMonitoringServer,
        labelStatus
        // Scoped to the panel: "Monitoring server" is also a column header,
        // which sits behind it.
      ].forEach((label) => {
        expect(within(panel).getAllByText(label)[0]).toBeVisible();
      });
    });

    it('sends the host group id as a query parameter', async () => {
      await initializeWithAdvancedFilters();

      await select(labelHostGroup, 'Linux servers', '@getHostGroups');

      await search();

      const { request } = await waitForRequest('@getAllHosts');

      expect(request.url.searchParams.get('group_id')).toBe('1');
    });

    it('sends the host template id as a query parameter', async () => {
      await initializeWithAdvancedFilters();

      await select(
        labelHostTemplate,
        'generic-active-host',
        '@getHostTemplates'
      );

      await search();

      const { request } = await waitForRequest('@getAllHosts');

      expect(request.url.searchParams.get('template_id')).toBe('5');
    });

    it('asks the host template filter for the locked templates as well', async () => {
      await initializeWithAdvancedFilters();

      await userEvent.click(screen.getByTestId(labelHostTemplate));

      const { request } = await waitForRequest('@getHostTemplates');

      expect(request.url.searchParams.get('is_locked')).toBe('true');
    });

    it('sends the monitoring server id as a query parameter', async () => {
      await initializeWithAdvancedFilters();

      await select(labelMonitoringServer, 'Poller EU', '@getPollers');

      await search();

      const { request } = await waitForRequest('@getAllHosts');

      expect(request.url.searchParams.get('poller_id')).toBe('2');
    });

    it('sends the status as `activated`, the parameter this endpoint takes', async () => {
      const panel = await initializeWithAdvancedFilters();

      const status = within(panel).getAllByText(labelStatus)[0];

      await userEvent.click(
        status.parentElement?.querySelector('input') as HTMLInputElement
      );

      await search();

      const { request } = await waitForRequest('@getAllHosts');

      expect(request.url.searchParams.get('activated')).toBe('true');
      expect(request.url.searchParams.get('is_activated')).toBeNull();
    });

    it('combines the filters, then drops them all with the clear action', async () => {
      await initializeWithAdvancedFilters();

      await select(labelHostGroup, 'Linux servers', '@getHostGroups');
      await select(
        labelHostTemplate,
        'generic-active-host',
        '@getHostTemplates'
      );

      await search();

      const { request: filtered } = await waitForRequest('@getAllHosts');

      expect(filtered.url.searchParams.get('group_id')).toBe('1');
      expect(filtered.url.searchParams.get('template_id')).toBe('5');

      await userEvent.click(screen.getByTestId(labelClear));

      const { request: cleared } = await waitForRequest('@getAllHosts');

      expect(cleared.url.searchParams.get('group_id')).toBeNull();
      expect(cleared.url.searchParams.get('template_id')).toBeNull();
    });

    it('keeps the filters in local storage so they survive navigation', async () => {
      await initializeWithAdvancedFilters();

      await select(labelHostGroup, 'Linux servers', '@getHostGroups');

      await search();

      await waitForRequest('@getAllHosts');

      await waitFor(() => {
        const stored = JSON.parse(
          window.localStorage.getItem('filters_hosts') as string
        );

        expect(stored.group_id).toEqual({ id: 1, name: 'Linux servers' });
      });
    });
  });
};
