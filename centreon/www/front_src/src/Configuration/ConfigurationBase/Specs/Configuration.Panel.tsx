import { capitalize } from '@mui/material';

import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { getDefaultPanelWidth, maxPanelWidth } from '../Panel';
import { panelDataTestIds } from '../Panel/dataTestIds';
import {
  labelClose,
  labelDelete,
  labelMoreActions,
  labelResourceDisabled
} from '../translatedLabels';
import initialize, {
  getDetailsResponse,
  mockActionsRequests,
  mockModalRequests,
  toResource
} from './initialize';
import { groups, inputs } from './utils';

interface Options {
  // The panel renders the same pixels whatever the resource type.
  hasSnapshots: boolean;
}

const getByTestIdInDocument = (dataTestId: string): HTMLElement | null =>
  document.querySelector(`[data-testid="${dataTestId}"]`);

const getPanelButton = (dataTestId: string): HTMLElement =>
  document.querySelector(`button[data-testid="${dataTestId}"]`) as HTMLElement;

const getEnableToggle = (): HTMLInputElement =>
  document.querySelector(
    `[data-testid="${panelDataTestIds.enable}"] input`
  ) as HTMLInputElement;

const findPanelContent = (): Promise<HTMLElement> =>
  screen.findByTestId(panelDataTestIds.content);

const findPanelHeader = (): Promise<HTMLElement> =>
  screen.findByTestId(panelDataTestIds.header);

// The surface itself, the paper around the content: a scrollbar narrows the
// content inside it.
const findPanelSurface = async (): Promise<HTMLElement> =>
  (await findPanelContent()).parentElement?.closest(
    '.MuiPaper-root'
  ) as HTMLElement;

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getField = (label: string): HTMLElement =>
  screen.getAllByTestId(label)[1];

const fillEveryField = async (): Promise<void> => {
  for (const { label } of inputs) {
    await userEvent.clear(getField(label));
    await userEvent.type(getField(label), `${label} abc`);
  }
};

const openForCreation = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId('add-resource'));
};

export default (resourceType: string, { hasSnapshots }: Options): void => {
  const resource = toResource(resourceType);
  const resourceName = `${resource} 1`;

  describe('Panel', () => {
    const mount = (options: Parameters<typeof initialize>[0] = {}): void => {
      initialize({ formVariant: 'panel', resourceType, ...options });
    };

    const waitForListing = async (): Promise<void> => {
      await waitForRequest('@getAll');
      await screen.findByText(`${resource} 0`);
    };

    const openForEdition = async (): Promise<void> => {
      await waitForListing();

      await userEvent.click(screen.getByText(resourceName));

      await waitForRequest('@getDetails');

      // The details applied: the form holds the loaded name.
      await waitFor(() => expect(getField('Name')).toHaveValue(resourceName));
    };

    beforeEach(() => {
      mockModalRequests(resource);
      mockActionsRequests(resource);
    });

    describe('Creation mode', () => {
      it("opens the panel in creation mode when the 'Add' button was clicked", async () => {
        mount();

        await waitForListing();

        await openForCreation();

        expect(await findPanelHeader()).toHaveTextContent(
          new RegExp(`^Add a ${resourceType}$`)
        );

        const save = getPanelButton(panelDataTestIds.save);

        expect(save).toBeVisible();
        expect(save).toBeDisabled();

        // The actions are in the header, so the form keeps none. Checked once
        // the panel shows.
        expect(
          document.querySelector('button[data-testid="submit"]')
        ).not.toBeInTheDocument();
        expect(
          document.querySelector('button[data-testid="cancel"]')
        ).not.toBeInTheDocument();

        if (hasSnapshots) {
          // What the screenshot showed: the empty form, its groups in order.
          inputs.forEach(({ label }) => {
            expect(getField(label)).toHaveValue('');
          });

          const [firstGroup, secondGroup] = groups.map(({ name }) =>
            screen.getByText(name)
          );

          expect(firstGroup.getBoundingClientRect().top).toBeLessThan(
            secondGroup.getBoundingClientRect().top
          );
        }
      });

      it('offers no resource action while creating', async () => {
        mount();

        await waitForListing();

        await openForCreation();

        // Checked once the panel shows its header actions.
        expect(
          await screen.findByTestId(panelDataTestIds.save)
        ).toBeInTheDocument();

        // Nothing to reset, enable, duplicate or delete before it exists.
        expect(
          getByTestIdInDocument(panelDataTestIds.reset)
        ).not.toBeInTheDocument();
        expect(
          getByTestIdInDocument(panelDataTestIds.enable)
        ).not.toBeInTheDocument();
        expect(
          getByTestIdInDocument(panelDataTestIds.duplicate)
        ).not.toBeInTheDocument();
        expect(
          getByTestIdInDocument(panelDataTestIds.delete)
        ).not.toBeInTheDocument();
      });

      it('runs from the top of the page to its bottom', async () => {
        mount();

        await waitForListing();

        await openForCreation();

        const page = document.querySelector('#page') as HTMLElement;
        const pageRect = page.getBoundingClientRect();

        // The rectangles below match the viewport too, so assert the
        // mechanism: a panel hanging off the wrong ancestor passes them.
        expect(getComputedStyle(page).position).toBe('relative');

        const panel = await findPanelSurface();

        await waitFor(() => {
          const panelRect = panel.getBoundingClientRect();

          expect(panelRect.top).toBe(pageRect.top);
          expect(panelRect.bottom).toBe(pageRect.bottom);
          expect(panelRect.right).toBe(pageRect.right);
        });
      });

      it('opens over the listing, leaving it at its width', async () => {
        mount();

        await waitForListing();

        const listing = document.querySelector('.MuiTable-root') as HTMLElement;
        const widthBeforeOpening = listing.getBoundingClientRect().width;

        await openForCreation();

        expect(await findPanelContent()).toBeVisible();

        await waitFor(() =>
          expect(
            (
              document.querySelector('.MuiTable-root') as HTMLElement
            ).getBoundingClientRect().width
          ).toBe(widthBeforeOpening)
        );
      });

      it('keeps the panel and its actions inside a page too narrow for it', async () => {
        // Cypress shrank the viewport to 700x590; here the page is given that
        // size, so its right edge stands for the viewport's.
        mount({ screenSize: { height: 590, width: 700 } });

        await waitForListing();

        await openForCreation();

        const pageRight = (
          document.querySelector('#page') as HTMLElement
        ).getBoundingClientRect().right;

        const panel = await findPanelContent();

        await waitFor(() => {
          expect(panel.getBoundingClientRect().right).toBeLessThanOrEqual(
            pageRight
          );
          expect(
            getPanelButton(panelDataTestIds.save).getBoundingClientRect().right
          ).toBeLessThanOrEqual(pageRight);

          // Narrower than asked: the clamp fired, rather than 720 fitting.
          expect(panel.getBoundingClientRect().width).toBeLessThan(720);
        });
      });

      // The screens the design sizes for, and what it opens the panel at.
      [
        { expected: 1400, viewport: 1920 },
        { expected: 900, viewport: 1512 },
        { expected: 900, viewport: 1440 },
        { expected: 720, viewport: 1280 }
      ].forEach(({ viewport, expected }) => {
        it(`opens at ${expected}px on a ${viewport}px screen`, async () => {
          mount({ screenSize: { height: 900, width: viewport } });

          await waitForListing();

          await openForCreation();

          const panel = await findPanelSurface();

          await waitFor(() => {
            expect(panel.getBoundingClientRect().width).toBe(expected);
            expect(expected).toBe(getDefaultPanelWidth(viewport));
          });
        });
      });

      it('reopens at the width it was last dragged to', async () => {
        mount({ panelWidth: 1000, screenSize: { height: 900, width: 1920 } });

        await waitForListing();

        await openForCreation();

        // Not the 1400 this screen opens at when nothing was ever dragged.
        const panel = await findPanelSurface();

        await waitFor(() =>
          expect(panel.getBoundingClientRect().width).toBe(1000)
        );

        await userEvent.click(screen.getByLabelText(labelClose));

        await waitFor(() =>
          expect(
            screen.queryByTestId(panelDataTestIds.content)
          ).not.toBeInTheDocument()
        );

        await openForCreation();

        const reopenedPanel = await findPanelSurface();

        await waitFor(() =>
          expect(reopenedPanel.getBoundingClientRect().width).toBe(1000)
        );
      });

      it('opens no wider than the design allows, whatever the module asks for', async () => {
        mount({
          formPanelWidth: 2000,
          screenSize: { height: 900, width: 1920 }
        });

        await waitForListing();

        await openForCreation();

        const panel = await findPanelSurface();

        await waitFor(() =>
          expect(panel.getBoundingClientRect().width).toBe(maxPanelWidth)
        );
      });

      it('shows form fields organized into groups, with each field initialized with default values', async () => {
        mount();

        await waitForListing();

        await openForCreation();

        await findPanelContent();

        groups.forEach(({ name }) => {
          expect(screen.getByText(name)).toBeVisible();
        });

        inputs.forEach(({ label }) => {
          expect(getField(label)).toBeVisible();
          expect(getField(label)).toHaveValue('');
        });
      });

      it('sends a POST request when the save action is clicked', async () => {
        mount();

        await waitForListing();

        await openForCreation();

        await findPanelContent();

        await fillEveryField();

        await userEvent.click(getPanelButton(panelDataTestIds.save));

        const { request } = await waitForRequest('@create');

        expect(request.body).toEqual({
          alias: 'Alias abc',
          coordinates: 'Coordinates abc',
          name: 'Name abc'
        });
      });
    });

    describe('Edition mode', () => {
      it('opens the panel in edition mode when a listing row was clicked', async () => {
        mount();

        await openForEdition();

        expect(await findPanelHeader()).toHaveTextContent(
          new RegExp(`^${resourceName}$`)
        );

        expect(getPanelButton(panelDataTestIds.save)).toBeDisabled();
        expect(getPanelButton(panelDataTestIds.duplicate)).toBeVisible();
        expect(getPanelButton(panelDataTestIds.delete)).toBeVisible();

        if (hasSnapshots) {
          // What the screenshot showed: the resource enabled, nothing to
          // reset yet, the loaded values in the form.
          expect(getEnableToggle()).toBeChecked();
          expect(getPanelButton(panelDataTestIds.reset)).toBeDisabled();

          const response = getDetailsResponse(resource);

          inputs.forEach(({ fieldName, label }) => {
            expect(getField(label)).toHaveValue(response[fieldName]);
          });
        }
      });

      it('shows form fields organized into groups, with each field initialized with the value received from the API', async () => {
        mount();

        await waitForListing();

        await userEvent.click(screen.getByText(resourceName));

        const { request } = await waitForRequest('@getDetails');

        // A module declaring no `baseEndpoint` must still read the default
        // one. Only Hosts overrides it, and nothing else may follow it there.
        expect(request.url.pathname).toContain('/api/latest/configuration/');

        const response = getDetailsResponse(resource);

        // The form renders again once the details are applied.
        await waitFor(() => {
          groups.forEach(({ name }) => {
            expect(screen.getByText(name)).toBeVisible();
          });

          inputs.forEach(({ fieldName, label }) => {
            expect(getField(label)).toHaveValue(response[fieldName]);
          });
        });
      });

      it('sends an UPDATE request when the save action is clicked', async () => {
        mount();

        await openForEdition();

        await fillEveryField();

        await userEvent.click(getPanelButton(panelDataTestIds.save));

        const { request } = await waitForRequest('@update');

        expect(request.body).toEqual({
          alias: 'Alias abc',
          coordinates: 'Coordinates abc',
          name: 'Name abc'
        });
      });

      it('restores the loaded values when the reset action is clicked', async () => {
        mount();

        await openForEdition();

        expect(getPanelButton(panelDataTestIds.reset)).toBeDisabled();

        await userEvent.clear(getField('Name'));
        await userEvent.type(getField('Name'), 'edited');

        await waitFor(() =>
          expect(getPanelButton(panelDataTestIds.reset)).toBeEnabled()
        );
        expect(getPanelButton(panelDataTestIds.save)).toBeEnabled();

        await userEvent.click(getPanelButton(panelDataTestIds.reset));

        // Discarding edits is confirmed, as deleting and closing are.
        await userEvent.click(await screen.findByTestId('confirm'));

        await waitFor(() => expect(getField('Name')).toHaveValue(resourceName));
        await waitFor(() =>
          expect(getPanelButton(panelDataTestIds.reset)).toBeDisabled()
        );
        expect(getPanelButton(panelDataTestIds.save)).toBeDisabled();
      });

      it('disables the open resource from the panel header', async () => {
        mount();

        await openForEdition();

        expect(getEnableToggle()).toBeChecked();

        await userEvent.click(getEnableToggle());

        const { request } = await waitForRequest('@disable');

        expect(request.body).toEqual({ ids: [1] });

        // The toggle flips on click: checked once the answer is applied, which
        // the success message shows, so a toggle put back would fail it.
        expect(
          await screen.findByText(
            labelResourceDisabled(capitalize(resourceType))
          )
        ).toBeVisible();
        expect(getEnableToggle()).not.toBeChecked();
      });

      it('puts the toggle back when the request is refused', async () => {
        // Registered last, so it answers instead of the one of `beforeEach`.
        mockActionsRequests(resource, true);

        mount();

        await openForEdition();

        expect(getEnableToggle()).toBeChecked();

        await userEvent.click(getEnableToggle());

        // Flipped at once, while the request is pending: so the check below
        // cannot pass on the state from before the click.
        await waitFor(() => expect(getEnableToggle()).not.toBeChecked());

        await waitForRequest('@disable');

        await waitFor(() => expect(getEnableToggle()).toBeChecked());
      });

      it('follows the listing when another row is clicked', async () => {
        mount();

        await openForEdition();

        expect(await findPanelHeader()).toHaveTextContent(
          new RegExp(`^${resourceName}$`)
        );

        // No detail response for this one: the panel has only the row to go on.
        const otherResource = `${resource} 3`;

        await userEvent.click(screen.getByText(otherResource));

        await waitFor(() =>
          expect(screen.getByTestId(panelDataTestIds.header)).toHaveTextContent(
            new RegExp(`^${otherResource}$`)
          )
        );
      });

      it('asks before another row takes the panel away from unsaved edits', async () => {
        mount();

        await openForEdition();

        await userEvent.clear(getField('Name'));
        await userEvent.type(getField('Name'), 'edited');

        await userEvent.click(screen.getByText(`${resource} 3`));

        expect(await screen.findByRole('dialog')).toBeVisible();

        expect(screen.getByTestId(panelDataTestIds.header)).toHaveTextContent(
          new RegExp(`^${resourceName}$`)
        );
      });

      it('closes the panel and clears the URL when the open resource is deleted', async () => {
        mount();

        await openForEdition();

        expect(window.location.search).toContain('id=1');

        await userEvent.click(getPanelButton(panelDataTestIds.delete));

        // Scoped: the listing row behind it carries the same text.
        expect(
          await within(await screen.findByRole('dialog')).findByText(
            resourceName
          )
        ).toBeVisible();

        await userEvent.click(screen.getByTestId('confirm'));

        await waitForRequest('@deleteOne');

        await waitFor(() =>
          expect(
            screen.queryByTestId(panelDataTestIds.content)
          ).not.toBeInTheDocument()
        );

        await waitFor(() => expect(window.location.search).toBe(''));
      });

      it('stays open when a delete does not name the resource it holds', async () => {
        mount();

        await openForEdition();

        // Rows are labelled by id; only activated ones are selectable.
        await userEvent.click(screen.getByLabelText('Select row 3'));
        await userEvent.click(screen.getByLabelText('Select row 5'));

        await userEvent.click(screen.getAllByTestId(labelMoreActions)[0]);

        const menu = (await screen.findAllByRole('menu')).at(-1) as HTMLElement;

        await userEvent.click(within(menu).getByText(labelDelete));
        await userEvent.click(await screen.findByTestId('confirm'));

        await waitForRequest('@delete');

        // Checked once the delete has been applied: the confirmation closed.
        await waitFor(() =>
          expect(screen.queryByTestId('confirm')).not.toBeInTheDocument()
        );

        expect(screen.getByTestId(panelDataTestIds.content)).toBeVisible();
        expect(window.location.search).toContain('id=1');
      });

      it('closes the panel when the close button is clicked', async () => {
        mount();

        await openForEdition();

        expect(await findPanelContent()).toBeVisible();

        await userEvent.click(screen.getByLabelText(labelClose));

        await waitFor(() =>
          expect(
            screen.queryByTestId(panelDataTestIds.content)
          ).not.toBeInTheDocument()
        );
      });
    });

    describe('Deep linking', () => {
      it('opens the panel in creation mode from the URL', async () => {
        mount({ searchParams: '?mode=add' });

        expect(await findPanelHeader()).toHaveTextContent(
          new RegExp(`^Add a ${resourceType}$`)
        );
      });

      it('opens the panel on the resource named by the URL', async () => {
        mount({ searchParams: '?mode=edit&id=1' });

        await waitForRequest('@getDetails');

        await waitFor(() =>
          expect(screen.getByTestId(panelDataTestIds.header)).toHaveTextContent(
            new RegExp(`^${resourceName}$`)
          )
        );
      });

      it('ignores the URL when the module offers neither edition nor details', async () => {
        mount({
          actions: {
            delete: () => true,
            duplicate: () => true,
            edit: false,
            enableDisable: () => true,
            massive: true,
            viewDetails: false
          },
          searchParams: '?mode=edit&id=1'
        });

        await waitForRequest('@getAll');

        // The URL is ignored, not the page.
        expect(await screen.findByText(resourceName)).toBeVisible();

        expect(
          screen.queryByTestId(panelDataTestIds.content)
        ).not.toBeInTheDocument();
      });

      it('opens a read-only panel for a module offering details without edition', async () => {
        mount({
          actions: {
            delete: () => false,
            duplicate: () => false,
            edit: false,
            enableDisable: () => false,
            viewDetails: true
          },
          searchParams: '?mode=edit&id=1'
        });

        await waitForRequest('@getDetails');

        expect(await findPanelContent()).toBeVisible();

        // Checked once the loaded resource heads the panel.
        await waitFor(() =>
          expect(screen.getByTestId(panelDataTestIds.header)).toHaveTextContent(
            new RegExp(`^${resourceName}$`)
          )
        );

        [
          panelDataTestIds.save,
          panelDataTestIds.reset,
          panelDataTestIds.duplicate,
          panelDataTestIds.delete,
          panelDataTestIds.enable
        ].forEach((dataTestId) => {
          expect(getByTestIdInDocument(dataTestId)).not.toBeInTheDocument();
        });
      });
    });
  });
};
