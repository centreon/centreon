import { waitForRequest } from '@test-setup/api';
import { screen } from '@testing-library/react';

import { labelActions, labelEnableDisable } from '../translatedLabels';
import initialize from './initialize';

// Every absence below is checked once the rows are shown: the columns render
// with them, so before that an absent column proves nothing.
const initializeListing = async (
  props: Parameters<typeof initialize>[0]
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAll');
  await screen.findByText('host 0');
};

// The shared per-row column and the toggle are the two cells a module does not
// write itself, so what they render is decided entirely by the actions it
// declares. These pin that contract, which three modules depend on.
export default () => {
  describe('Static columns', () => {
    it('offers neither column when the module declares no row action', async () => {
      await initializeListing({ actions: { edit: true } });

      expect(screen.queryByText(labelActions)).not.toBeInTheDocument();
      expect(screen.queryByText(labelEnableDisable)).not.toBeInTheDocument();
    });

    it('offers the actions column, and not the toggle, for delete alone', async () => {
      await initializeListing({ actions: { delete: () => true, edit: true } });

      expect(screen.getByText(labelActions)).toBeVisible();
      expect(screen.queryByText(labelEnableDisable)).not.toBeInTheDocument();
    });

    it('hides the toggle on rows the predicate refuses', async () => {
      await initializeListing({
        actions: { edit: true, enableDisable: (row) => row?.id === 1 }
      });

      expect(screen.getByTestId(`${labelEnableDisable}_1`)).toBeInTheDocument();
      expect(
        screen.queryByTestId(`${labelEnableDisable}_2`)
      ).not.toBeInTheDocument();
    });

    it('disables those rows instead when the module keeps them without write access', async () => {
      await initializeListing({
        actions: {
          edit: true,
          enableDisable: (row) => row?.id === 1,
          rowActionsWithoutWriteAccess: true
        }
      });

      expect(
        screen.getByTestId(`${labelEnableDisable}_1`).querySelector('input')
      ).toBeEnabled();
      expect(
        screen.getByTestId(`${labelEnableDisable}_2`).querySelector('input')
      ).toBeDisabled();
    });

    it('renders a module row action, filtered by its own visibility predicate', async () => {
      await initializeListing({
        actions: {
          edit: true,
          rowActions: [
            {
              dataTestId: (row) => `custom-action_${row.id}`,
              Icon: () => <span>action</span>,
              isVisible: (row) => row.id === 1,
              label: 'Custom action',
              onClick: () => undefined
            }
          ]
        }
      });

      expect(screen.getByTestId('custom-action_1')).toBeInTheDocument();
      expect(screen.queryByTestId('custom-action_2')).not.toBeInTheDocument();
    });
  });
};
