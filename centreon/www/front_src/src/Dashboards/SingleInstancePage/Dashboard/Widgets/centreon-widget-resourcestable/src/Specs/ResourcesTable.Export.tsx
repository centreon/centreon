import { rs } from '@rstest/core';
import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelCancel,
  labelCurrentPageOnly,
  labelExport,
  labelExportProcessingInProgress,
  labelExportToCSV,
  labelFilterRessources,
  labelVisibleColumnsOnly,
  labelWarningExportToCsv
} from '../../../../../../../Resources/translatedLabels';
import { exportResourcesEndpoint } from '../api/endpoints';
import { DisplayType } from '../Listing/models';
import { labelViewByHost } from '../Listing/translatedLabels';
import initialize from './initialize';
import { allColumnIds, selectedColumnIds } from './utils';

// The query parameters selecting the resources: the export must send the same
// ones as the listing.
const filterParameters = [
  'types',
  'statuses',
  'status_types',
  'host_severity_names',
  'service_severity_names',
  'states',
  'hostgroup_names',
  'search'
];

const initializeWidget = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<URL> => {
  initialize(props);

  const { request } = await waitForRequest('@getResources');
  await screen.findByText('Service 1');

  return request.url;
};

const spyOnWindowOpen = (): ReturnType<typeof rs.spyOn> =>
  rs.spyOn(window, 'open').mockImplementation(() => null);

const getExportButton = (): HTMLElement =>
  screen.getByRole('button', { name: labelExportToCSV });

const openExportDialog = async (): Promise<HTMLElement> => {
  await userEvent.click(getExportButton());

  return screen.findByRole('dialog');
};

const getExportedUrl = (open: ReturnType<typeof rs.spyOn>): URL => {
  expect(open).toHaveBeenCalledTimes(1);

  return new URL(open.mock.calls[0][0] as string, window.location.href);
};

export default () => {
  describe('CSV export: ', () => {
    it('does not display the export action when the export is disabled', async () => {
      await initializeWidget({ options: { isExportEnabled: false } });

      expect(screen.getByLabelText(labelViewByHost)).toBeInTheDocument();
      expect(
        screen.queryByRole('button', { name: labelExportToCSV })
      ).not.toBeInTheDocument();
    });

    it('does not display the export action when the dashboard is being edited', async () => {
      await initializeWidget({ isEditingDashboard: true });

      expect(screen.getByLabelText(labelViewByHost)).toBeInTheDocument();
      expect(
        screen.queryByRole('button', { name: labelExportToCSV })
      ).not.toBeInTheDocument();
    });

    it('does not display the export action in the widget preview', async () => {
      await initializeWidget({ isFromPreview: true });

      expect(screen.getByLabelText(labelViewByHost)).toBeInTheDocument();
      expect(
        screen.queryByRole('button', { name: labelExportToCSV })
      ).not.toBeInTheDocument();
    });

    it('does not display the export action in a playlist', async () => {
      await initializeWidget({ playlistHash: 'hash' });

      expect(screen.getByLabelText(labelViewByHost)).toBeInTheDocument();
      expect(
        screen.queryByRole('button', { name: labelExportToCSV })
      ).not.toBeInTheDocument();
    });

    it('disables the export action in the "by service" display', async () => {
      await initializeWidget({
        options: { displayType: DisplayType.Service }
      });

      expect(getExportButton()).toBeDisabled();
    });

    it('disables the export action in the "by host" display', async () => {
      initialize({ options: { displayType: DisplayType.Host } });

      await waitForRequest('@getResourcesByHost');

      await waitFor(() => {
        expect(getExportButton()).toBeDisabled();
      });
    });

    it('exports all the columns of all the pages with the filters of the widget', async () => {
      const listingUrl = await initializeWidget();
      const open = spyOnWindowOpen();

      const dialog = await openExportDialog();

      const { request } = await waitForRequest('@getResourcesCount');

      expect(request.url.searchParams.get('all_pages')).toBe('true');
      filterParameters.forEach((name) => {
        expect(request.url.searchParams.get(name)).toBe(
          listingUrl.searchParams.get(name)
        );
      });

      expect(await within(dialog).findByText('25')).toBeInTheDocument();
      expect(
        within(dialog).getByText(labelWarningExportToCsv)
      ).toBeInTheDocument();
      expect(
        within(dialog).queryByText(labelFilterRessources)
      ).not.toBeInTheDocument();

      await userEvent.click(
        within(dialog).getByRole('button', { name: labelExport })
      );

      expect(
        await screen.findByText(labelExportProcessingInProgress)
      ).toBeInTheDocument();

      const exportedUrl = getExportedUrl(open);

      expect(exportedUrl.pathname).toContain(
        `/api/latest${exportResourcesEndpoint}`
      );
      expect(exportedUrl.searchParams.get('format')).toBe('csv');
      expect(exportedUrl.searchParams.get('all_pages')).toBe('true');
      expect(exportedUrl.searchParams.get('max_lines')).toBe('10000');
      expect(exportedUrl.searchParams.getAll('columns[]')).toEqual(
        allColumnIds
      );
      [...filterParameters, 'sort_by'].forEach((name) => {
        expect(exportedUrl.searchParams.get(name)).toBe(
          listingUrl.searchParams.get(name)
        );
      });
      expect(exportedUrl.searchParams.get('types')).toBe(
        '["host","service","metaservice"]'
      );
      expect(exportedUrl.searchParams.get('statuses')).toBe(
        '["DOWN","CRITICAL","WARNING"]'
      );
      expect(exportedUrl.searchParams.get('hostgroup_names')).toBe('["HG1"]');
      expect(exportedUrl.searchParams.get('search')).toBe(
        '{"$and":[{"$or":[{"name":{"$rg":"^Serv"}}]}]}'
      );
    });

    it('exports the visible columns of the current page', async () => {
      await initializeWidget();
      const open = spyOnWindowOpen();

      await userEvent.click(screen.getByLabelText('Next page'));

      const { request: listingRequest } = await waitForRequest('@getResources');

      expect(listingRequest.url.searchParams.get('page')).toBe('2');

      const dialog = await openExportDialog();

      await userEvent.click(
        within(dialog).getByLabelText(labelVisibleColumnsOnly)
      );
      await userEvent.click(
        within(dialog).getByLabelText(labelCurrentPageOnly)
      );

      await waitFor(async () => {
        const { request } = await waitForRequest('@getResourcesCount');

        expect(request.url.searchParams.get('all_pages')).toBe('false');
        expect(request.url.searchParams.get('page')).toBe('2');
        expect(request.url.searchParams.get('limit')).toBe('10');
      });

      await userEvent.click(
        within(dialog).getByRole('button', { name: labelExport })
      );

      const exportedUrl = getExportedUrl(open);

      expect(exportedUrl.searchParams.get('all_pages')).toBe('false');
      expect(exportedUrl.searchParams.getAll('columns[]')).toEqual(
        selectedColumnIds
      );
      ['page', 'limit', 'sort_by', ...filterParameters].forEach((name) => {
        expect(exportedUrl.searchParams.get(name)).toBe(
          listingRequest.url.searchParams.get(name)
        );
      });
    });

    it('warns that the export is limited when more than 10,000 resources match', async () => {
      await initializeWidget({ count: 12000 });

      const dialog = await openExportDialog();

      expect(await within(dialog).findByText('12000')).toBeInTheDocument();
      expect(
        within(dialog).getByText(labelFilterRessources)
      ).toBeInTheDocument();
    });

    it('does not export anything when the export is cancelled', async () => {
      await initializeWidget();
      const open = spyOnWindowOpen();

      const dialog = await openExportDialog();

      await waitForRequest('@getResourcesCount');
      await userEvent.click(
        await within(dialog).findByRole('button', { name: labelCancel })
      );

      await waitFor(() => {
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      });
      expect(open).not.toHaveBeenCalled();
    });
  });
};
