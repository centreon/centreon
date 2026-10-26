import { capitalize } from '@mui/material';

import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import pluralize from 'pluralize';

import {
  labelCancel,
  labelDelete,
  labelDeleteResource,
  labelDisable,
  labelDuplicate,
  labelDuplicateResource,
  labelDuplications,
  labelEnable,
  labelEnableDisable,
  labelMoreActions,
  labelResourceDeleted,
  labelResourceDisabled,
  labelResourceDuplicated,
  labelResourceEnabled
} from '../translatedLabels';
import initialize, { mockActionsRequests, toResource } from './initialize';
import {
  findByWholeText,
  getLabelDeleteMany,
  getLabelDeleteOne,
  getLabelDuplicateMany,
  getLabelDuplicateOne
} from './utils';

const getRowCheckbox = (id: number): HTMLElement =>
  screen.getByLabelText(`Select row ${id}`);

// Row 2 is deactivated: its checkbox is disabled, but the cell around it still
// selects the row. The click lands on the cell, as a user's does.
const selectRows = async (): Promise<void> => {
  await userEvent.click(getRowCheckbox(1));
  await userEvent.click(
    getRowCheckbox(2).closest('.MuiTableCell-root') as HTMLElement
  );
  await userEvent.click(getRowCheckbox(3));
};

const openMoreActions = async (): Promise<HTMLElement> => {
  await userEvent.click(screen.getByTestId(labelMoreActions));

  // The last one opened: a closing menu may still be in the page.
  return (await screen.findAllByRole('menu')).at(-1) as HTMLElement;
};

// What the confirmation dialog shows besides its title and sentence.
const expectDialogButtons = async (confirmLabel: string): Promise<void> => {
  const dialog = await screen.findByRole('dialog');

  expect(within(dialog).getByTestId('confirm')).toHaveTextContent(
    new RegExp(`^${confirmLabel}$`)
  );
  expect(within(dialog).getByText(labelCancel)).toBeVisible();
};

// Once the success snackbar appeared: the dialog closed.
const expectDialogClosed = async (): Promise<void> => {
  await waitFor(() =>
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  );
};

// Once the success snackbar appeared: the dialog closed, and the selection
// cleared with it. The selection is read from More actions, which needs one:
// the last clicked row checkbox can keep showing its previous state until its
// row renders again, so it proves nothing here.
const expectDialogClosedAndSelectionCleared = async (): Promise<void> => {
  await expectDialogClosed();
  expect(screen.getByTestId(labelMoreActions)).toBeDisabled();
};

export default (resourceType: string): void => {
  const resource = toResource(resourceType);

  describe('Actions: ', () => {
    beforeEach(async () => {
      initialize({ resourceType });
      mockActionsRequests(resource);

      await waitForRequest('@getAll');
      await screen.findByText(`${resource} 0`);
    });

    it('enables the more actions button when selecting resources', async () => {
      expect(screen.getAllByTestId(labelMoreActions)[0]).toBeDisabled();

      await selectRows();

      await waitFor(() =>
        expect(screen.getAllByTestId(labelMoreActions)[0]).toBeEnabled()
      );
    });

    describe('Delete', () => {
      it('displays the confirmation dialog when the inline delete button is clicked', async () => {
        await userEvent.click(screen.getByTestId(`${labelDelete}_1`));

        expect(
          await screen.findByText(labelDeleteResource(resourceType))
        ).toBeVisible();
        expect(
          await findByWholeText(
            getLabelDeleteOne(resourceType, `${resource} 1`)
          )
        ).toBeVisible();

        await expectDialogButtons(labelDelete);
      });

      it('displays the confirmation dialog when the massive delete button is clicked', async () => {
        await selectRows();

        const menu = await openMoreActions();

        await userEvent.click(within(menu).getByText(labelDelete));

        expect(
          await screen.findByText(labelDeleteResource(pluralize(resourceType)))
        ).toBeVisible();

        expect(
          await findByWholeText(getLabelDeleteMany(pluralize(resourceType), 3))
        ).toBeVisible();

        await expectDialogButtons(labelDelete);
      });

      it('sends a delete request for a single resource and displays a success message', async () => {
        await userEvent.click(screen.getByTestId(`${labelDelete}_1`));

        await userEvent.click(await screen.findByTestId('confirm'));

        await waitForRequest('@deleteOne');
        await waitForRequest('@getAll');

        expect(
          await screen.findByText(
            labelResourceDeleted(capitalize(resourceType))
          )
        ).toBeVisible();

        await expectDialogClosed();
      });

      it('sends a delete request for multiple resources and displays a success message', async () => {
        await selectRows();

        const menu = await openMoreActions();

        await userEvent.click(within(menu).getByText(labelDelete));

        await userEvent.click(await screen.findByTestId('confirm'));

        const { request } = await waitForRequest('@delete');

        expect(request.body).toEqual({
          ids: [1, 2, 3]
        });

        await waitForRequest('@getAll');

        expect(
          await screen.findByText(
            labelResourceDeleted(pluralize(capitalize(resourceType)))
          )
        ).toBeVisible();

        await expectDialogClosedAndSelectionCleared();
      });
    });

    describe('duplicate', () => {
      it('displays the confirmation dialog when the inline duplicate button is clicked', async () => {
        await userEvent.click(screen.getByTestId(`${labelDuplicate}_1`));

        expect(
          await screen.findByText(labelDuplicateResource(resourceType))
        ).toBeVisible();

        expect(
          await findByWholeText(
            getLabelDuplicateOne(resourceType, `${resource} 1`)
          )
        ).toBeVisible();

        expect(screen.getByText(labelDuplications)).toBeVisible();

        // One duplication by default.
        expect(screen.getAllByLabelText(labelDuplications)[0]).toHaveValue(1);
        await expectDialogButtons(labelDuplicate);
      });

      it('displays the confirmation dialog when the massive duplicate button is clicked', async () => {
        await selectRows();

        const menu = await openMoreActions();

        await userEvent.click(within(menu).getByText(labelDuplicate));

        expect(
          await screen.findByText(
            labelDuplicateResource(pluralize(resourceType))
          )
        ).toBeVisible();

        expect(
          await findByWholeText(
            getLabelDuplicateMany(pluralize(resourceType), 3)
          )
        ).toBeVisible();

        expect(screen.getByText(labelDuplications)).toBeVisible();

        expect(screen.getAllByLabelText(labelDuplications)[0]).toHaveValue(1);
        await expectDialogButtons(labelDuplicate);
      });

      it('sends a duplicate request for a single resource and displays a success message', async () => {
        await userEvent.click(screen.getByTestId(`${labelDuplicate}_1`));

        await userEvent.click(await screen.findByTestId('confirm'));

        const { request } = await waitForRequest('@duplicate');

        expect(request.body).toEqual({
          ids: [1],
          nb_duplicates: 1
        });

        await waitForRequest('@getAll');

        expect(
          await screen.findByText(
            labelResourceDuplicated(capitalize(resourceType))
          )
        ).toBeVisible();

        await expectDialogClosed();
      });

      it('sends a duplicate request for multiple resources and displays a success message', async () => {
        await selectRows();

        const menu = await openMoreActions();

        await userEvent.click(within(menu).getByText(labelDuplicate));

        await userEvent.click(await screen.findByTestId('confirm'));

        const { request } = await waitForRequest('@duplicate');

        expect(request.body).toEqual({
          ids: [1, 2, 3],
          nb_duplicates: 1
        });

        await waitForRequest('@getAll');

        expect(
          await screen.findByText(
            labelResourceDuplicated(pluralize(capitalize(resourceType)))
          )
        ).toBeVisible();

        await expectDialogClosedAndSelectionCleared();
      });

      it('sends a duplicate request for multiple resources with a custom number of duplications and displays a success message', async () => {
        await selectRows();

        const menu = await openMoreActions();

        await userEvent.click(within(menu).getByText(labelDuplicate));

        const duplications = (
          await screen.findAllByLabelText(labelDuplications)
        )[0];

        expect(duplications).toHaveValue(1);

        await userEvent.clear(duplications);
        await userEvent.type(duplications, '4');

        await userEvent.click(screen.getByTestId('confirm'));

        const { request } = await waitForRequest('@duplicate');

        expect(request.body).toEqual({
          ids: [1, 2, 3],
          nb_duplicates: 4
        });

        await waitForRequest('@getAll');

        expect(
          await screen.findByText(
            labelResourceDuplicated(pluralize(capitalize(resourceType)))
          )
        ).toBeVisible();

        await expectDialogClosedAndSelectionCleared();
      });
    });

    describe('Enable', () => {
      it('sends an Enable request for a single resource and displays a success message', async () => {
        await userEvent.click(screen.getByTestId(`${labelEnableDisable}_2`));

        const { request } = await waitForRequest('@enable');

        expect(request.body).toEqual({
          ids: [2]
        });

        await waitForRequest('@getAll');

        expect(
          await screen.findByText(
            labelResourceEnabled(capitalize(resourceType))
          )
        ).toBeVisible();
      });

      it('sends an enable request for multiple resources and displays a success message', async () => {
        await selectRows();

        const menu = await openMoreActions();

        await userEvent.click(within(menu).getByLabelText(labelEnable));

        const { request } = await waitForRequest('@enable');

        expect(request.body).toEqual({
          ids: [1, 2, 3]
        });

        await waitForRequest('@getAll');

        expect(
          await screen.findByText(
            labelResourceEnabled(pluralize(capitalize(resourceType)))
          )
        ).toBeVisible();

        await expectDialogClosedAndSelectionCleared();
      });
    });

    describe('Disable', () => {
      it('sends a disable request for a single resource and displays a success message', async () => {
        await userEvent.click(screen.getByTestId(`${labelEnableDisable}_1`));

        const { request } = await waitForRequest('@disable');

        expect(request.body).toEqual({
          ids: [1]
        });

        await waitForRequest('@getAll');

        expect(
          await screen.findByText(
            labelResourceDisabled(capitalize(resourceType))
          )
        ).toBeVisible();
      });

      it('sends a disbale request for multiple resources and displays a success message', async () => {
        await selectRows();

        const menu = await openMoreActions();

        await userEvent.click(within(menu).getByLabelText(labelDisable));

        const { request } = await waitForRequest('@disable');

        expect(request.body).toEqual({
          ids: [1, 2, 3]
        });

        await waitForRequest('@getAll');

        expect(
          await screen.findByText(
            labelResourceDisabled(pluralize(capitalize(resourceType)))
          )
        ).toBeVisible();

        await expectDialogClosedAndSelectionCleared();
      });
    });
  });
};
