import { rs } from '@rstest/core';
import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelDelete,
  labelDisable,
  labelDuplicate,
  labelEnable,
  labelEnableDisable,
  labelMassChange,
  labelMoreActions
} from '../../ConfigurationBase/translatedLabels';
import {
  labelDeployServices,
  labelServiceDeploymentFailed,
  labelServicesDeployed
} from '../translatedLabels';
import initialize from './initialize';

const getCheckboxes = (): Array<HTMLInputElement> => [
  ...document.querySelectorAll<HTMLInputElement>('input[type="checkbox"]')
];

// A deactivated row is not selectable, so indexing the enabled checkboxes is
// what actually maps to "the nth selectable host". Index 0 is select-all.
const selectSelectableRow = async (index: number): Promise<void> => {
  await userEvent.click(
    getCheckboxes().filter((checkbox) => !checkbox.disabled)[index + 1]
  );
};

const selectFirstRow = (): Promise<void> => selectSelectableRow(0);

const selectAll = async (): Promise<void> => {
  await userEvent.click(getCheckboxes()[0]);
};

const openMoreActions = async (): Promise<HTMLElement> => {
  await userEvent.click(screen.getByTestId(labelMoreActions));

  // The last one opened: a closing menu may still be in the page.
  return (await screen.findAllByRole('menu')).at(-1) as HTMLElement;
};

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHosts');
  await screen.findByText('host 0');
};

export default () => {
  describe('Actions: ', () => {
    it('links the services icon to the legacy services listing for that host', async () => {
      await initializeListing();

      const open = rs.spyOn(window, 'open').mockImplementation(() => null);

      await userEvent.click(screen.getByTestId('go-to-services_0'));

      expect(open).toHaveBeenCalledWith(
        '/main.php?p=60201&search=host%200',
        '_self'
      );
    });

    it('deletes a host through the confirmation modal', async () => {
      await initializeListing();

      await userEvent.click(screen.getByTestId(`${labelDelete}_0`));

      await userEvent.click(await screen.findByTestId('confirm'));

      // API Platform, not the legacy prefix — the same assertion the listing
      // makes, since the two are not interchangeable.
      const { request } = await waitForRequest('@deleteHost');

      expect(request.url.pathname).toContain('/api/configuration/hosts/0');
      expect(request.url.pathname).not.toContain('/api/latest');

      // What the screenshot of the end state showed: the modal is closed.
      await waitFor(() =>
        expect(screen.queryByTestId('confirm')).not.toBeInTheDocument()
      );
    });

    it('duplicates a host through the confirmation modal', async () => {
      await initializeListing();

      await userEvent.click(screen.getByTestId(`${labelDuplicate}_0`));

      await userEvent.click(await screen.findByTestId('confirm'));

      // The route names the host and takes no body.
      const { request } = await waitForRequest('@duplicateHost');

      expect(request.url.pathname).toContain(
        '/api/configuration/hosts/0/_duplicate'
      );
      expect(request.url.pathname).not.toContain('/api/latest');
    });

    it('deletes every selected host, one request each', async () => {
      await initializeListing();

      await selectAll();

      const menu = await openMoreActions();

      await userEvent.click(within(menu).getByText(labelDelete));
      await userEvent.click(await screen.findByTestId('confirm'));

      // Sequentially, so waiting on the last one proves the whole fan-out ran.
      await waitForRequest('@deleteHost');
      const { request } = await waitForRequest('@deleteHost2');

      expect(request.url.pathname).toContain('/api/configuration/hosts/2');
    });

    it('disables an activated host from the row toggle', async () => {
      await initializeListing();

      await userEvent.click(screen.getByTestId(`${labelEnableDisable}_0`));

      const { request } = await waitForRequest('@patchHost');

      expect(request.url.pathname).not.toContain('/api/latest');
      expect(request.body).toEqual({ activated: false });
    });

    it('enables a deactivated host from the row toggle', async () => {
      await initializeListing();

      await userEvent.click(screen.getByTestId(`${labelEnableDisable}_1`));

      // `activated`, the key the endpoint reads: any other is ignored.
      const { request } = await waitForRequest('@patchHost1');

      expect(request.body).toEqual({ activated: true });
    });

    it('offers deploy services in the More actions menu', async () => {
      await initializeListing();

      await selectFirstRow();

      const menu = await openMoreActions();
      const deployServices = within(menu).getByText(labelDeployServices);

      await waitFor(() => expect(deployServices).toBeVisible());
      await userEvent.click(deployServices);

      await waitForRequest('@deployServices');

      expect(
        await screen.findByText(labelServicesDeployed)
      ).toBeInTheDocument();
    });

    it('reports a failed deployment with the agreed message, once', async () => {
      await initializeListing({ deployFails: true });

      await selectFirstRow();

      const menu = await openMoreActions();

      await userEvent.click(within(menu).getByText(labelDeployServices));

      await waitForRequest('@deployServices');

      expect(
        await screen.findByText(labelServiceDeploymentFailed)
      ).toBeInTheDocument();

      // The API's own message is suppressed so the two do not stack.
      expect(screen.queryByText('Host not found')).not.toBeInTheDocument();
      expect(screen.queryByText(labelServicesDeployed)).not.toBeInTheDocument();
    });

    it('offers every massive action the ticket lists', async () => {
      await initializeListing();

      await selectFirstRow();

      const menu = await openMoreActions();

      // A positive assertion on the whole menu: it fails if an entry goes
      // missing or one appears.
      expect(
        within(menu)
          .getAllByRole('menuitem')
          .map((entry) => entry.textContent)
      ).toEqual([
        labelDuplicate,
        labelEnable,
        labelDisable,
        labelDelete,
        labelMassChange,
        labelDeployServices
      ]);
    });

    it('disables every selected host, not just the first', async () => {
      await initializeListing();

      // Select-all takes every row, the deactivated host 1 included although
      // its own checkbox is greyed.
      await selectAll();

      const menu = await openMoreActions();

      await userEvent.click(within(menu).getByText(labelDisable));

      // Waiting on the second host is the whole point: patching only `ids[0]`
      // while reporting the entire selection as done is the bug this pins, and
      // it is invisible if you assert on the first host.
      const { request } = await waitForRequest('@patchHost2');

      expect(request.body).toEqual({ activated: false });
    });

    describe('Read-only ACL user: ', () => {
      beforeEach(async () => {
        await initializeListing({ hasWriteAccess: false });
      });

      it('keeps the services icon and the toggle, the latter disabled', () => {
        expect(screen.getByTestId('go-to-services_0')).toBeVisible();

        expect(
          within(screen.getByTestId(`${labelEnableDisable}_0`)).getByRole(
            'checkbox'
          )
        ).toBeDisabled();
      });

      it('hides the add button, more actions and the row write actions', () => {
        expect(screen.queryByTestId(labelMoreActions)).not.toBeInTheDocument();
        expect(screen.queryByTestId('add-resource')).not.toBeInTheDocument();
        expect(
          screen.queryByTestId(`${labelDelete}_0`)
        ).not.toBeInTheDocument();
        expect(
          screen.queryByTestId(`${labelDuplicate}_0`)
        ).not.toBeInTheDocument();
      });
    });
  });
};
