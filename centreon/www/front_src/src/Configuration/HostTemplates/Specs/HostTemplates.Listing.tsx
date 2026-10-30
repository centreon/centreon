import { rs } from '@rstest/core';
import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { labelSearch } from '../../ConfigurationBase/translatedLabels';
import {
  labelAlias,
  labelCreateHostTemplate,
  labelHostTemplates,
  labelLinkedServiceTemplates,
  labelName,
  labelTemplates,
  labelWelcomeToHostTemplates
} from '../translatedLabels';
import initialize from './initialize';
import { hostTemplateIcon } from './utils';

// The listing's own empty row, from `@centreon/ui` (not exported from it).
const labelNoResultFound = 'No result found';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<ReturnType<typeof initialize>> => {
  const result = initialize(props);

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('generic-active-host-custom');

  return result;
};

export default () => {
  describe('Listing: ', () => {
    it('reads the listing from API Platform, sorted by name, unlocked only', async () => {
      initialize();

      // The intercept glob matches the legacy base too, so assert the URL.
      const { request } = await waitForRequest('@getAllHostTemplates');

      expect(request.url.pathname).toContain(
        '/api/configuration/host_templates'
      );
      expect(request.url.pathname).not.toContain('/api/latest');
      expect(request.url.searchParams.get('page')).toBe('1');
      expect(request.url.searchParams.get('itemsPerPage')).toBe('10');
      expect(request.url.searchParams.get('sort[name]')).toBe('asc');
      // Absent, not `false`: the endpoint then lists unlocked templates only.
      expect(request.url.searchParams.has('is_locked')).toBe(false);

      expect(screen.getByLabelText('page header title')).toHaveTextContent(
        labelHostTemplates
      );
    });

    it('displays the host templates returned by the API', async () => {
      await initializeListing();

      expect(screen.getByText('generic-active-host-custom')).toBeVisible();
      expect(screen.getByText('Generic active host')).toBeVisible();
      expect(screen.getByText('OS-Linux-SNMP-custom')).toBeVisible();
    });

    // The fixture carries the JSON-LD `@id`/`@type` API Platform adds to each
    // member and nested object: decoding ignores them rather than failing.
    it('decodes the rows, icons and parents despite their JSON-LD keys', async () => {
      await initializeListing();

      expect(screen.getByText('OS-Linux-SNMP-custom')).toBeVisible();
      expect(screen.getByTestId('host-template-link_4')).toBeVisible();
      // The image replaces its placeholder once loaded.
      await waitFor(() => {
        expect(screen.getByTestId('centreon.png')).toHaveAttribute(
          'src',
          hostTemplateIcon
        );
      });
    });

    it('sorts by name descending when the name column is clicked', async () => {
      await initializeListing();

      await userEvent.click(screen.getByLabelText(`Column ${labelName}`));

      const { request } = await waitForRequest('@getAllHostTemplates');

      expect(request.url.searchParams.get('sort[name]')).toBe('desc');
    });

    it('sorts back ascending on a second click of the same column', async () => {
      await initializeListing();

      await userEvent.click(screen.getByLabelText(`Column ${labelName}`));

      const { request: first } = await waitForRequest('@getAllHostTemplates');

      expect(first.url.searchParams.get('sort[name]')).toBe('desc');

      await userEvent.click(screen.getByLabelText(`Column ${labelName}`));

      const { request: second } = await waitForRequest('@getAllHostTemplates');

      expect(second.url.searchParams.get('sort[name]')).toBe('asc');
    });

    it('sorts by alias when the alias column is clicked', async () => {
      await initializeListing();

      await userEvent.click(screen.getByLabelText(`Column ${labelAlias}`));

      const { request } = await waitForRequest('@getAllHostTemplates');

      // A newly picked column sorts descending first, as on every listing.
      expect(request.url.searchParams.get('sort[alias]')).toBe('desc');
      expect(request.url.searchParams.get('sort[name]')).toBeNull();
    });

    it('offers no sort on the linked service templates and templates columns', async () => {
      await initializeListing();

      // The column labels are there, so their missing sort control is not.
      expect(screen.getAllByText(labelLinkedServiceTemplates)[0]).toBeVisible();
      expect(screen.getAllByText(labelTemplates)[0]).toBeVisible();

      expect(
        screen.queryByLabelText(`Column ${labelLinkedServiceTemplates}`)
      ).not.toBeInTheDocument();
      expect(
        screen.queryByLabelText(`Column ${labelTemplates}`)
      ).not.toBeInTheDocument();
    });

    it('requests the next page', async () => {
      await initializeListing();

      await userEvent.click(screen.getByLabelText('Next page'));

      const { request } = await waitForRequest('@getAllHostTemplates');

      expect(request.url.searchParams.get('page')).toBe('2');
    });

    it('sends the page size the user picks', async () => {
      await initializeListing();

      await userEvent.click(
        within(await screen.findByTestId('Listing Pagination')).getByRole(
          'combobox'
        )
      );
      await userEvent.click(await screen.findByRole('option', { name: '20' }));

      const { request } = await waitForRequest('@getAllHostTemplates');

      expect(request.url.searchParams.get('itemsPerPage')).toBe('20');
    });

    it('displays the welcome page when no host template exists', async () => {
      initialize({ isEmpty: true });

      await waitForRequest('@getAllHostTemplates');

      expect(
        await screen.findByText(labelWelcomeToHostTemplates)
      ).toBeVisible();

      const emptyState = screen.getByTestId('data-table-empty-state');

      expect(
        within(emptyState).getByRole('heading', {
          name: labelWelcomeToHostTemplates
        })
      ).toBeVisible();

      const create = within(emptyState).getByRole('button', { name: 'create' });

      expect(create).toHaveTextContent(
        new RegExp(`^${labelCreateHostTemplate}$`)
      );
      expect(create).toBeEnabled();
    });

    // Filters that match nothing are not an empty platform: the listing
    // stays, with its own empty row, rather than the welcome page.
    it('keeps the listing, empty, when the filters match no host template', async () => {
      window.localStorage.setItem(
        'filters_host_templates',
        JSON.stringify({ is_locked: false, name: 'nothing matches' })
      );

      initialize({ isEmpty: true });

      await waitForRequest('@getAllHostTemplates');

      expect(await screen.findByText(labelNoResultFound)).toBeVisible();
      expect(
        screen.queryByText(labelWelcomeToHostTemplates)
      ).not.toBeInTheDocument();
      expect(screen.getByPlaceholderText(labelSearch)).toHaveValue(
        'nothing matches'
      );
    });

    it('offers no way to create from the welcome page without write access', async () => {
      initialize({ hasWriteAccess: false, isEmpty: true });

      await waitForRequest('@getAllHostTemplates');

      expect(
        await screen.findByText(labelWelcomeToHostTemplates)
      ).toBeVisible();
      expect(
        screen.queryByText(labelCreateHostTemplate)
      ).not.toBeInTheDocument();
    });

    // A bookmark or a reload: the page opens straight on that template.
    it('opens the panel of the template named in the URL it is opened at', async () => {
      const { queryErrors } = initialize({ search: '?id=2&mode=edit' });

      await waitForRequest('@getAllHostTemplates');

      expect(await screen.findByTestId('form-panel')).toBeVisible();
      expect(window.location.search).toBe('?id=2&mode=edit');
      expect(queryErrors).toEqual([]);
    });

    // No detail endpoint exists until the form sub-task: the row opens an
    // empty panel, without querying an undefined URL.
    it('opens the panel of the clicked row without requesting its details', async () => {
      const { queryErrors } = await initializeListing();

      const fetchSpy = rs.spyOn(window, 'fetch');

      await userEvent.click(screen.getByText('Generic active host'));

      expect(await screen.findByTestId('form-panel')).toBeVisible();
      expect(window.location.search).toBe('?id=1&mode=edit');

      // Long enough for a detail query to have run and failed.
      await new Promise((resolve) => {
        setTimeout(resolve, 500);
      });

      expect(fetchSpy).not.toHaveBeenCalled();
      expect(queryErrors).toEqual([]);
      expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
  });
};
