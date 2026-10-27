import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelDelete,
  labelDisable,
  labelDuplicate,
  labelDuplications,
  labelEnable,
  labelEnableDisable,
  labelMoreActions
} from '../../ConfigurationBase/translatedLabels';
import initialize from './initialize';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('Generic active host');
};

const openMoreActions = async (): Promise<HTMLElement> => {
  await userEvent.click(screen.getByTestId(labelMoreActions));

  // The last one opened: a closing menu may still be in the page.
  return (await screen.findAllByRole('menu')).at(-1) as HTMLElement;
};

export default () => {
  describe('Actions: ', () => {
    it('deletes a template through the confirmation modal', async () => {
      await initializeListing();

      await userEvent.click(screen.getByTestId(`${labelDelete}_5`));
      await userEvent.click(await screen.findByTestId('confirm'));

      const { request } = await waitForRequest('@deleteHostTemplate5');

      expect(request.url.pathname).toContain(
        '/api/configuration/host_templates/5'
      );
      expect(request.url.pathname).not.toContain('/api/latest');
    });

    it('duplicates a template without asking for a number of copies', async () => {
      await initializeListing();

      await userEvent.click(screen.getByTestId(`${labelDuplicate}_5`));

      const confirm = await screen.findByTestId('confirm');

      // The route takes no body, as the hosts one.
      expect(
        screen.queryByLabelText(labelDuplications)
      ).not.toBeInTheDocument();

      await userEvent.click(confirm);

      const { request } = await waitForRequest('@duplicateHostTemplate5');

      expect(request.url.pathname).toContain(
        '/api/configuration/host_templates/5/_duplicate'
      );
    });

    it('offers neither delete nor duplicate on a locked template', async () => {
      await initializeListing();

      expect(screen.getByTestId(`${labelDelete}_5`)).toBeInTheDocument();
      expect(screen.queryByTestId(`${labelDelete}_8`)).not.toBeInTheDocument();
      expect(
        screen.queryByTestId(`${labelDuplicate}_8`)
      ).not.toBeInTheDocument();
    });

    it('has no enable or disable: a template has no status', async () => {
      await initializeListing();

      expect(
        screen.queryByTestId(`${labelEnableDisable}_5`)
      ).not.toBeInTheDocument();

      await userEvent.click(screen.getByLabelText('Select row 5'));

      const menu = await openMoreActions();

      expect(within(menu).getByText(labelDelete)).toBeVisible();
      expect(within(menu).getByText(labelDuplicate)).toBeVisible();
      expect(within(menu).queryByText(labelEnable)).not.toBeInTheDocument();
      expect(within(menu).queryByText(labelDisable)).not.toBeInTheDocument();
    });

    it('does not let a locked template be selected, not even by select all', async () => {
      await initializeListing();

      expect(screen.getByLabelText('Select row 8')).toBeDisabled();
      expect(screen.getByLabelText('Select row 5')).toBeEnabled();

      await userEvent.click(screen.getByLabelText('Select all'));

      // Templates 5 and 6, without the locked 8.
      await waitFor(() =>
        expect(screen.getByLabelText('Select row 6')).toBeChecked()
      );
      expect(screen.getByLabelText('Select row 5')).toBeChecked();
      expect(screen.getByLabelText('Select row 8')).not.toBeChecked();
    });

    it('offers no write action without the host template write access', async () => {
      await initializeListing({ hasWriteAccess: false });

      expect(screen.queryByTestId('add-resource')).not.toBeInTheDocument();
      expect(screen.queryByTestId(`${labelDelete}_5`)).not.toBeInTheDocument();
      await waitFor(() =>
        expect(screen.queryByLabelText('Select row 5')).not.toBeInTheDocument()
      );
    });
  });
};
