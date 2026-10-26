import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelClear,
  labelFilters,
  labelSearch
} from '../../ConfigurationBase/translatedLabels';
import {
  labelHostName,
  labelIncludeDisabledHosts,
  labelName,
  labelServiceTemplate,
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

const search = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId(labelSearch));
};

const initializeWithAdvancedFilters = async (): Promise<HTMLElement> => {
  initialize();

  await waitForRequest('@getAllServices');

  return openAdvancedFilters();
};

const typeIn = async (
  panel: HTMLElement,
  label: string,
  text: string
): Promise<void> => {
  await userEvent.type(within(panel).getByLabelText(label), text);
};

export default () => {
  describe('Filters: ', () => {
    it('offers the filters of the listing', async () => {
      const panel = await initializeWithAdvancedFilters();

      [
        labelName,
        labelHostName,
        labelServiceTemplate,
        labelStatus,
        labelIncludeDisabledHosts
      ].forEach((label) => {
        expect(within(panel).getAllByText(label)[0]).toBeVisible();
      });
    });

    it('opens on the services of the host named in the URL, then leaves the URL', async () => {
      localStorage.setItem(
        'filters_services',
        JSON.stringify({
          disabled: false,
          enabled: false,
          'host.name': '',
          include_disabled_hosts: false,
          name: 'cpu',
          template_id: null
        })
      );

      initialize({ searchParams: '?host=host%20A' });

      // The first request already carries it: no listing of the stored
      // filters flashes by.
      const { request } = await waitForRequest('@getAllServices');

      expect(request.url.searchParams.get('host.name[lk]')).toBe('host A');
      // The link replaces the stored filters.
      expect(request.url.searchParams.get('name[lk]')).toBeNull();

      await waitFor(() =>
        expect(new URLSearchParams(window.location.search).has('host')).toBe(
          false
        )
      );

      const panel = await openAdvancedFilters();

      expect(within(panel).getByLabelText(labelHostName)).toHaveValue('host A');
    });

    it('searches on the service name and the host name separately', async () => {
      const panel = await initializeWithAdvancedFilters();

      await typeIn(panel, labelName, 'cpu');
      await typeIn(panel, labelHostName, 'host A');

      await search();

      const { request } = await waitForRequest('@getAllServices');

      expect(request.url.searchParams.get('name[lk]')).toBe('cpu');
      expect(request.url.searchParams.get('host.name[lk]')).toBe('host A');
    });

    it('sends the service template id as a query parameter', async () => {
      await initializeWithAdvancedFilters();

      await userEvent.click(screen.getByTestId(labelServiceTemplate));
      await waitForRequest('@getServiceTemplates');
      await waitFor(() => expect(getPopper()).not.toBeNull());
      await userEvent.click(
        await within(getPopper()).findByText('generic-passive-service')
      );

      await search();

      const { request } = await waitForRequest('@getAllServices');

      expect(request.url.searchParams.get('template_id')).toBe('31');
    });

    it('sends the status as `activated`', async () => {
      const panel = await initializeWithAdvancedFilters();

      const status = within(panel).getAllByText(labelStatus)[0];

      await userEvent.click(
        status.parentElement?.querySelector('input') as HTMLInputElement
      );

      await search();

      const { request } = await waitForRequest('@getAllServices');

      expect(request.url.searchParams.get('activated')).toBe('true');
    });

    it('leaves out the services of disabled hosts unless asked', async () => {
      initialize();

      const { request: initial } = await waitForRequest('@getAllServices');

      expect(initial.url.searchParams.get('include_disabled_hosts')).toBeNull();

      await openAdvancedFilters();

      await userEvent.click(screen.getByTestId(labelIncludeDisabledHosts));

      await search();

      const { request } = await waitForRequest('@getAllServices');

      expect(request.url.searchParams.get('include_disabled_hosts')).toBe(
        'true'
      );
    });

    it('drops every filter with the clear action', async () => {
      const panel = await initializeWithAdvancedFilters();

      await typeIn(panel, labelHostName, 'host A');

      await search();

      await waitForRequest('@getAllServices');

      await userEvent.click(screen.getByTestId(labelClear));

      const { request } = await waitForRequest('@getAllServices');

      // An empty text filter is left out of the query altogether.
      expect(request.url.searchParams.get('host.name[lk]')).toBeNull();
    });

    it('keeps the filters in local storage so they survive navigation', async () => {
      const panel = await initializeWithAdvancedFilters();

      await typeIn(panel, labelHostName, 'host A');

      await search();

      await waitForRequest('@getAllServices');

      await waitFor(() => {
        const stored = JSON.parse(
          window.localStorage.getItem('filters_services') as string
        );

        expect(stored['host.name']).toBe('host A');
      });
    });
  });
};
