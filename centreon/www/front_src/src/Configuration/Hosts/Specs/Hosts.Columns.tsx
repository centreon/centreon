import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';

import {
  labelAlias,
  labelIpAddress,
  labelMonitoringServer,
  labelName,
  labelTemplates
} from '../translatedLabels';
import initialize from './initialize';
import { hostIcon } from './utils';

const getRow = (hostName: string): HTMLElement =>
  screen.getByText(hostName).closest('[role="row"]') as HTMLElement;

export default () => {
  describe('Columns: ', () => {
    beforeEach(async () => {
      initialize();

      await waitForRequest('@getAllHosts');
      await screen.findByText('host 0');
    });

    it('displays every column of the listing', () => {
      [
        labelName,
        labelAlias,
        labelIpAddress,
        labelMonitoringServer,
        labelTemplates
      ].forEach((label) => {
        expect(screen.getAllByText(label)[0]).toBeVisible();
      });

      expect(screen.getByText('host 0')).toBeVisible();
      expect(screen.getByText('alias for host 0')).toBeVisible();
      expect(screen.getByText('10.0.0.0')).toBeVisible();
      expect(within(getRow('host 0')).getByText('Central')).toBeVisible();
    });

    it('renders the icon returned by the API, at the URL it returned', async () => {
      // Asserting the exact src is what proves no prefix is prepended: the
      // platform mount path is empty when Centreon is served at the root.
      // The image replaces its placeholder once loaded.
      await waitFor(() => {
        const icon = screen.getByTestId('server.png');

        expect(icon).toBeVisible();
        expect(icon).toHaveAttribute('src', hostIcon);
      });
    });

    it('falls back to the default host icon when the host has no icon', async () => {
      await waitFor(() =>
        expect(screen.getByTestId('server.png')).toHaveAttribute(
          'src',
          hostIcon
        )
      );

      // Scoped to the row: a page-wide count passes for the wrong reason and
      // breaks whenever a fixture row is added.
      expect(
        within(getRow('host 1')).getByTestId('HostIcon')
      ).toBeInTheDocument();
    });

    it('links each template to its configuration page', () => {
      const activeTemplate = screen.getByTestId('host-template-link_5');

      expect(activeTemplate).toHaveTextContent(/^generic-active-host$/);
      expect(activeTemplate).toHaveAttribute(
        'href',
        '/main.php?p=60103&o=c&host_id=5'
      );

      const passiveTemplate = screen.getByTestId('host-template-link_6');

      expect(passiveTemplate).toHaveTextContent(/^generic-passive-host$/);
      expect(passiveTemplate).toHaveAttribute(
        'href',
        '/main.php?p=60103&o=c&host_id=6'
      );

      // The separator is only in the rendered text.
      expect(getRow('host 0')).toHaveTextContent(
        'generic-active-host | generic-passive-host'
      );
    });

    it('leaves the templates cell empty when the host inherits from none', () => {
      expect(
        getRow('host 1').querySelector('[data-testid^="host-template-link_"]')
      ).toBeNull();

      // The links exist elsewhere, so the row above is empty for the right
      // reason rather than because the column is missing.
      expect(screen.getByTestId('host-template-link_5')).toBeInTheDocument();
    });
  });
};
