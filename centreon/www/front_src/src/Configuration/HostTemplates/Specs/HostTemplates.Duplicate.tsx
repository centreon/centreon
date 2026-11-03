import {
  getRequestCalls,
  interceptAPIRequest,
  Method,
  waitForRequest
} from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { selectedRowsAtom } from '../../ConfigurationBase/Listing/atoms';
import {
  labelDuplicate,
  labelDuplications,
  labelMoreActions,
  labelRequired
} from '../../ConfigurationBase/translatedLabels';
import { getDuplicateHostTemplateEndpoint } from '../api/endpoints';
import initialize from './initialize';

const nameInputSelector = 'input[data-testid="duplicate-name"]';
// The backend's error body: `{ code, message }`.
const nameTaken = 'A host or host template already uses this name.';
const nameTooLong =
  '[name] This value is too long. It should have 200 characters or less.\n';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<ReturnType<typeof initialize>> => {
  const result = initialize(props);

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('generic-active-host-custom');

  return result;
};

// After `initialize`: the latest handler for a path wins.
const answerDuplicate = ({
  id,
  message,
  statusCode
}: {
  id: number;
  message: string;
  statusCode: number;
}): void =>
  interceptAPIRequest({
    alias: `duplicateHostTemplate${id}`,
    method: Method.POST,
    path: `**${getDuplicateHostTemplateEndpoint({ id })}`,
    response: { code: 0, message },
    statusCode
  });

const openNameDialog = async (id: number): Promise<HTMLInputElement> => {
  await userEvent.click(screen.getByTestId(`${labelDuplicate}_${id}`));

  await waitFor(() =>
    expect(document.querySelector(nameInputSelector)).not.toBeNull()
  );

  return document.querySelector(nameInputSelector) as HTMLInputElement;
};

const submitName = async (name?: string): Promise<void> => {
  if (name !== undefined) {
    const input = document.querySelector(nameInputSelector) as HTMLElement;

    await userEvent.clear(input);
    await userEvent.type(input, name);
  }

  await userEvent.click(screen.getByTestId('submit'));
};

// `findByRole` outlasts its timeout on the listing's DOM: query the snackbars directly.
const getSnackbarTexts = (): Array<string | null> =>
  Array.from(document.querySelectorAll('[role="alert"]')).map(
    ({ textContent }) => textContent
  );

const listingCalls = (): number =>
  getRequestCalls('getAllHostTemplates').length;

export default () => {
  describe('Duplicate from a row: ', () => {
    it('asks the name, prefilled, without a copy count', async () => {
      await initializeListing();

      const input = await openNameDialog(1);

      expect(input).toHaveValue('generic-active-host-custom_1');
      expect(screen.queryByText(labelDuplications)).not.toBeInTheDocument();
    });

    it('sends exactly the typed name, then refetches and closes', async () => {
      await initializeListing();
      const callsBefore = listingCalls();

      await openNameDialog(1);
      await submitName('my copy');

      const { request } = await waitForRequest('@duplicateHostTemplate1');

      expect(request.url.pathname).toBe(
        '/api/configuration/host_templates/1/_duplicate'
      );
      expect(request.body).toEqual({ name: 'my copy' });
      expect(await screen.findByText('Host template duplicated')).toBeVisible();
      await waitFor(() => expect(listingCalls()).toBeGreaterThan(callsBefore));
      await waitFor(() =>
        expect(document.querySelector(nameInputSelector)).toBeNull()
      );
    });

    it('prefills from the row it is opened on', async () => {
      await initializeListing();

      await openNameDialog(1);
      await userEvent.click(screen.getByTestId('cancel'));
      await waitFor(() =>
        expect(document.querySelector(nameInputSelector)).toBeNull()
      );

      expect(await openNameDialog(2)).toHaveValue('generic-active-host_1');
    });

    it('clears the selection on cancel and sends nothing', async () => {
      const { store } = await initializeListing();

      await userEvent.click(screen.getByLabelText('Select row 2'));
      await openNameDialog(1);
      await userEvent.click(screen.getByTestId('cancel'));

      await waitFor(() => expect(store.get(selectedRowsAtom)).toEqual([]));
      expect(getRequestCalls('duplicateHostTemplate1')).toHaveLength(0);
    });

    it('refuses an empty name without sending anything', async () => {
      await initializeListing();

      await openNameDialog(1);
      await userEvent.clear(
        document.querySelector(nameInputSelector) as HTMLElement
      );
      await userEvent.tab();

      expect(await screen.findByText(labelRequired)).toBeVisible();
      expect(screen.getByTestId('submit')).toBeDisabled();
      expect(getRequestCalls('duplicateHostTemplate1')).toHaveLength(0);
    });

    [
      { message: nameTaken, statusCode: 409 },
      { message: nameTooLong, statusCode: 422 }
    ].forEach(({ message, statusCode }) => {
      it(`shows a ${statusCode} on the name field and keeps the dialog open`, async () => {
        await initializeListing();
        answerDuplicate({ id: 1, message, statusCode });

        await openNameDialog(1);
        await submitName();
        await waitForRequest('@duplicateHostTemplate1');

        const dialog = await screen.findByRole('dialog');

        expect(await within(dialog).findByText(message.trim())).toBeVisible();
        expect(document.querySelector(nameInputSelector)).not.toBeNull();
        expect(getSnackbarTexts()).toEqual([]);
      });
    });

    it('shows any other error as a snackbar, not on the name field', async () => {
      await initializeListing();
      answerDuplicate({
        id: 1,
        message: 'Host template not found',
        statusCode: 404
      });

      await openNameDialog(1);
      await submitName();
      await waitForRequest('@duplicateHostTemplate1');

      const message = await screen.findByText('Host template not found');

      expect(message.closest('[role="alert"]')).not.toBeNull();
      expect(
        within(screen.getByRole('dialog')).queryByText(
          'Host template not found'
        )
      ).not.toBeInTheDocument();
      expect(document.querySelector(nameInputSelector)).not.toBeNull();
    });
  });

  describe('Duplicate a selection: ', () => {
    const duplicateSelection = async (): Promise<void> => {
      await userEvent.click(screen.getByLabelText('Select row 1'));
      await userEvent.click(screen.getByLabelText('Select row 2'));
      await userEvent.click(screen.getByTestId(labelMoreActions));

      const menu = (await screen.findAllByRole('menu')).at(-1) as HTMLElement;

      await userEvent.click(within(menu).getByText(labelDuplicate));
    };

    it('confirms the count, then sends one request per template without a body', async () => {
      const { store } = await initializeListing();
      const callsBefore = listingCalls();

      await duplicateSelection();

      expect(
        await screen.findByText('2 host templates.', { selector: 'strong' })
      ).toBeVisible();
      expect(document.querySelector(nameInputSelector)).toBeNull();

      await userEvent.click(screen.getByTestId('confirm'));

      const first = await waitForRequest('@duplicateHostTemplate1');
      const second = await waitForRequest('@duplicateHostTemplate2');

      expect(first.request.body).toBeUndefined();
      expect(second.request.body).toBeUndefined();
      expect(
        await screen.findByText('Host templates duplicated')
      ).toBeVisible();
      expect(store.get(selectedRowsAtom)).toEqual([]);
      await waitFor(() => expect(listingCalls()).toBeGreaterThan(callsBefore));
    });

    it('names the template that failed and still duplicates the others', async () => {
      await initializeListing();
      answerDuplicate({ id: 2, message: nameTaken, statusCode: 409 });

      await duplicateSelection();
      await userEvent.click(await screen.findByTestId('confirm'));

      await waitForRequest('@duplicateHostTemplate1');
      await waitForRequest('@duplicateHostTemplate2');

      const warning = await screen.findByText(
        'Failed to duplicate: generic-active-host'
      );

      expect(warning.closest('[role="alert"]')).not.toBeNull();
    });

    // The row dialog's own handling of 409 must not reach the selection.
    it('keeps reporting the server reason when every template fails', async () => {
      await initializeListing();
      answerDuplicate({ id: 1, message: nameTaken, statusCode: 409 });
      answerDuplicate({ id: 2, message: nameTaken, statusCode: 409 });

      await duplicateSelection();
      await userEvent.click(await screen.findByTestId('confirm'));

      await waitForRequest('@duplicateHostTemplate1');
      await waitForRequest('@duplicateHostTemplate2');

      expect(
        await screen.findByText('Failed to duplicate the host templates')
      ).toBeVisible();
      await waitFor(() =>
        expect(getSnackbarTexts()).toEqual(
          expect.arrayContaining([expect.stringContaining(nameTaken)])
        )
      );
    });
  });
};
