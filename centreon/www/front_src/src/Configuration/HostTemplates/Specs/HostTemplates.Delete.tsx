import {
  getRequestCalls,
  interceptAPIRequest,
  Method,
  waitForRequest
} from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { selectedRowsAtom } from '../../ConfigurationBase/Listing/atoms';
import { listingDataTestIds } from '../../ConfigurationBase/Listing/dataTestIds';
import { labelDelete } from '../../ConfigurationBase/translatedLabels';
import { getHostTemplateEndpoint } from '../api/endpoints';
import initialize from './initialize';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<ReturnType<typeof initialize>> => {
  const result = initialize(props);

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('generic-active-host-custom');

  return result;
};

const answerDelete = ({
  id,
  message,
  statusCode
}: {
  id: number;
  message: string;
  statusCode: number;
}): void =>
  interceptAPIRequest({
    alias: `deleteHostTemplate${id}`,
    method: Method.DELETE,
    path: `**${getHostTemplateEndpoint({ id })}`,
    response: { code: statusCode, message },
    statusCode
  });

const deleteSelection = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId(listingDataTestIds.moreActions));

  const menu = (await screen.findAllByRole('menu')).at(-1) as HTMLElement;

  await userEvent.click(within(menu).getByText(labelDelete));
  await userEvent.click(await screen.findByTestId('confirm'));
};

const listingCalls = (): number =>
  getRequestCalls('getAllHostTemplates').length;

export default () => {
  describe('Delete: ', () => {
    it('deletes a template from its row through the confirmation', async () => {
      await initializeListing();
      const callsBefore = listingCalls();

      await userEvent.click(
        screen.getByTestId(listingDataTestIds.row.delete(1))
      );

      expect(
        await screen.findByText('generic-active-host-custom', {
          selector: 'strong'
        })
      ).toBeVisible();

      await userEvent.click(screen.getByTestId('confirm'));

      const { request } = await waitForRequest('@deleteHostTemplate1');

      expect(request.url.pathname).toBe('/api/configuration/host_templates/1');
      expect(await screen.findByText('Host template deleted')).toBeVisible();
      await waitFor(() => expect(listingCalls()).toBeGreaterThan(callsBefore));
      expect(getRequestCalls('deleteHostTemplate2')).toHaveLength(0);
    });

    it('deletes a selection with one request per template, then clears it', async () => {
      const { store } = await initializeListing();
      const callsBefore = listingCalls();

      await userEvent.click(screen.getByLabelText('Select row 1'));
      await userEvent.click(screen.getByLabelText('Select row 2'));
      await deleteSelection();

      await waitForRequest('@deleteHostTemplate1');
      await waitForRequest('@deleteHostTemplate2');

      expect(await screen.findByText('Host templates deleted')).toBeVisible();
      expect(store.get(selectedRowsAtom)).toEqual([]);
      await waitFor(() => expect(listingCalls()).toBeGreaterThan(callsBefore));
    });

    it('reports the server reason when a row deletion fails', async () => {
      await initializeListing();
      answerDelete({
        id: 1,
        message: 'Host template not found',
        statusCode: 404
      });

      await userEvent.click(
        screen.getByTestId(listingDataTestIds.row.delete(1))
      );
      await userEvent.click(await screen.findByTestId('confirm'));
      await waitForRequest('@deleteHostTemplate1');

      const message = await screen.findByText('Host template not found');

      expect(message.closest('[role="alert"]')).not.toBeNull();
      expect(
        screen.queryByText('Host template deleted')
      ).not.toBeInTheDocument();
    });

    it('names the template that failed and still deletes the others', async () => {
      await initializeListing();
      answerDelete({
        id: 2,
        message: 'Host template not found',
        statusCode: 404
      });

      await userEvent.click(screen.getByLabelText('Select row 1'));
      await userEvent.click(screen.getByLabelText('Select row 2'));
      await deleteSelection();

      await waitForRequest('@deleteHostTemplate1');
      await waitForRequest('@deleteHostTemplate2');

      const warning = await screen.findByText(
        'Failed to delete: generic-active-host'
      );

      expect(warning.closest('[role="alert"]')).not.toBeNull();
    });

    it('never sends the locked template, even after select-all', async () => {
      await initializeListing();

      await userEvent.click(screen.getByLabelText('Select all'));
      await deleteSelection();

      await waitForRequest('@deleteHostTemplate2');

      expect(getRequestCalls('deleteHostTemplate1')).toHaveLength(1);
      expect(getRequestCalls('deleteHostTemplate3')).toHaveLength(0);
    });
  });
};
