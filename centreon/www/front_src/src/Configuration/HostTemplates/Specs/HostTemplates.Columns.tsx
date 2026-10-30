import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelActions,
  labelEnableDisable
} from '../../ConfigurationBase/translatedLabels';
import {
  labelAlias,
  labelLinkedServiceTemplates,
  labelName,
  labelTemplates
} from '../translatedLabels';
import initialize, { getHeaderLabels } from './initialize';
import { hostTemplateIcon } from './utils';

const getRow = (name: string): HTMLElement =>
  screen.getByText(name).closest('[role="row"]') as HTMLElement;

// The parent links repeat a template name, so a row is found by a text only
// its own name cell holds.
const getRowOf = (id: number): HTMLElement =>
  screen
    .getByLabelText(`Select row ${id}`)
    .closest('[role="row"]') as HTMLElement;

export default () => {
  describe('Columns: ', () => {
    beforeEach(async () => {
      initialize();

      await waitForRequest('@getAllHostTemplates');
      await screen.findByText('generic-active-host-custom');
    });

    it('displays the columns of the listing, in order, without a status column', () => {
      const headers = getHeaderLabels();

      expect(headers).toEqual([
        labelName,
        labelAlias,
        labelLinkedServiceTemplates,
        labelTemplates,
        labelActions
      ]);
      expect(screen.queryByText(labelEnableDisable)).not.toBeInTheDocument();
    });

    it('displays the alias, or nothing when the template has none', () => {
      expect(
        within(getRow('generic-active-host-custom')).getByText(
          'Generic active host'
        )
      ).toBeVisible();
      expect(within(getRowOf(3)).getByText('Linux SNMP')).toBeVisible();
    });

    it('displays the linked service templates count as plain text', () => {
      expect(within(getRowOf(1)).getByText(/^3$/)).toBeVisible();
      expect(within(getRowOf(2)).getByText(/^0$/)).toBeVisible();

      const count = within(getRowOf(3)).getByText(/^12$/);

      expect(count).toBeVisible();
      // Not clickable: no link, no button around the number.
      expect(count.closest('a, button')).toBeNull();
    });

    it('renders the icon returned by the API, at the URL it returned', async () => {
      // The image replaces its placeholder once loaded.
      await waitFor(() => {
        const icon = screen.getByTestId('centreon.png');

        expect(icon).toBeVisible();
        expect(icon).toHaveAttribute('src', hostTemplateIcon);
      });
    });

    it('falls back to the default host icon when the template has no icon', async () => {
      await waitFor(() =>
        expect(screen.getByTestId('centreon.png')).toHaveAttribute(
          'src',
          hostTemplateIcon
        )
      );

      // Left out of the payload, and explicitly null.
      expect(within(getRowOf(2)).getByTestId('HostIcon')).toBeInTheDocument();
      expect(within(getRowOf(3)).getByTestId('HostIcon')).toBeInTheDocument();
      expect(within(getRowOf(1)).queryByTestId('HostIcon')).toBeNull();
    });

    it('lists the direct parent templates, separated by a pipe', () => {
      const row = getRowOf(3);

      expect(row).toHaveTextContent('generic-active-host | OS-Linux-SNMP');

      expect(within(row).getByTestId('host-template-link_2')).toHaveTextContent(
        /^generic-active-host$/
      );
      expect(within(row).getByTestId('host-template-link_4')).toHaveTextContent(
        /^OS-Linux-SNMP$/
      );
    });

    it('leaves the templates cell empty when the template inherits from none', () => {
      expect(
        getRowOf(2).querySelector('[data-testid^="host-template-link_"]')
      ).toBeNull();

      // The links exist elsewhere, so the row above is empty for the right
      // reason rather than because the column is missing.
      expect(
        within(getRowOf(1)).getByTestId('host-template-link_2')
      ).toBeInTheDocument();
    });

    it('links a parent template to its own panel on this page, not the legacy form', () => {
      const link = within(getRowOf(1)).getByTestId('host-template-link_2');
      const href = new URL(
        link.getAttribute('href') as string,
        window.location.href
      );

      expect(href.pathname).toBe(window.location.pathname);
      expect(href.searchParams.get('id')).toBe('2');
      expect(href.searchParams.get('mode')).toBe('edit');
      expect(link.getAttribute('href')).not.toContain('main.php');
    });

    it('opens the parent template, not the row, when its link is clicked', async () => {
      await userEvent.click(
        within(getRowOf(3)).getByTestId('host-template-link_4')
      );

      expect(await screen.findByTestId('form-panel')).toBeVisible();
      expect(window.location.search).toBe('?id=4&mode=edit');
    });
  });

  describe('Columns, set up per test: ', () => {
    it('opens with the column selection kept in local storage', async () => {
      window.localStorage.setItem(
        'columns_host_templates',
        JSON.stringify(['name', 'parent_templates', 'actions'])
      );

      initialize();

      await waitForRequest('@getAllHostTemplates');
      await screen.findByText('generic-active-host-custom');

      expect(getHeaderLabels()).toEqual([
        labelName,
        labelTemplates,
        labelActions
      ]);
    });

    // No test of an icon that fails to load: `@centreon/ui`'s `Image` shows
    // its fallback only while loading, then a broken image (`useLoadImage`
    // records a failure as `''`, which it reads as loaded). Shared behavior,
    // the Hosts listing included; to fix there, not to pin here.
  });
};
