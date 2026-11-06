import { capitalize } from '@mui/material';

import { waitForRequest } from '@test-setup/api';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../Panel/dataTestIds';
import {
  labelCancel,
  labelResourceCreated,
  labelResourceUpdated,
  labelSave
} from '../translatedLabels';
import initialize, {
  getDetailsResponse,
  mockModalRequests,
  toResource
} from './initialize';
import { groups, inputs } from './utils';

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getField = (label: string): HTMLElement =>
  screen.getAllByTestId(label)[1];

const getSubmitButton = (): HTMLElement =>
  document.querySelector('button[data-testid="submit"]') as HTMLElement;

const fillEveryField = async (): Promise<void> => {
  for (const { label } of inputs) {
    await userEvent.clear(getField(label));
    await userEvent.type(getField(label), `${label} abc`);
  }
};

export default (resourceType: string): void => {
  const resource = toResource(resourceType);

  describe('Modal', () => {
    beforeEach(async () => {
      mockModalRequests(resource);

      initialize({ resourceType });

      await waitForRequest('@getAll');
      await screen.findByText(`${resource} 0`);
    });

    const openForCreation = async (): Promise<void> => {
      await userEvent.click(screen.getByTestId('add-resource'));

      await screen.findByText(`Add a ${resourceType}`);
    };

    const openForEdition = async (): Promise<void> => {
      await userEvent.click(screen.getByText(`${resource} 1`));

      await waitForRequest('@getDetails');
    };

    describe('Creation mode', () => {
      it("opens the modal in creation mode when the 'Add' button was clicked", async () => {
        await userEvent.click(screen.getByTestId('add-resource'));

        expect(await screen.findByText(`Add a ${resourceType}`)).toBeVisible();

        const submit = getSubmitButton();

        expect(submit).toBeVisible();
        expect(submit).toHaveTextContent(new RegExp(`^${labelSave}$`));
        expect(submit).toBeDisabled();

        // Opt-in: a module that asks for nothing keeps the modal. Checked once
        // the modal shows.
        expect(
          screen.queryByTestId(panelDataTestIds.content)
        ).not.toBeInTheDocument();

        // The form can be cancelled.
        expect(screen.getByText(labelCancel)).toBeVisible();

        await userEvent.click(screen.getByLabelText('close'));
      });

      it('shows form fields organized into groups, with each field initialized with default values', async () => {
        await openForCreation();

        groups.forEach(({ name }) => {
          expect(screen.getByText(name)).toBeVisible();
        });

        inputs.forEach(({ label }) => {
          expect(getField(label)).toBeVisible();
          expect(getField(label)).toHaveValue('');
        });

        await userEvent.click(screen.getByLabelText('close'));
      });

      it('sends a POST request when the Create Button is clicked', async () => {
        await openForCreation();

        await fillEveryField();

        await userEvent.click(getSubmitButton());

        const { request } = await waitForRequest('@create');

        expect(request.body).toEqual({
          alias: 'Alias abc',
          coordinates: 'Coordinates abc',
          name: 'Name abc'
        });

        // The modal closed on a success message.
        expect(
          await screen.findByText(
            labelResourceCreated(capitalize(resourceType))
          )
        ).toBeVisible();
        await waitFor(() =>
          expect(
            screen.queryByText(`Add a ${resourceType}`)
          ).not.toBeInTheDocument()
        );
      });
    });

    describe('Edition mode', () => {
      it('opens the modal in edition mode when the a listing row button was clicked', async () => {
        await openForEdition();

        expect(
          await screen.findByText(`Modify a ${resourceType}`)
        ).toBeVisible();

        // The label can still be changing when the title shows.
        await waitFor(() => {
          expect(getSubmitButton()).toHaveTextContent(
            new RegExp(`^${labelSave}$`)
          );
        });
        expect(getSubmitButton()).toBeDisabled();

        // The form can be cancelled.
        expect(screen.getByText(labelCancel)).toBeVisible();

        await userEvent.click(screen.getByLabelText('close'));
      });

      it('shows form fields organized into groups, with each field initialized with the value received from the API', async () => {
        await openForEdition();

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

        await userEvent.click(screen.getByLabelText('close'));
      });

      it('sends an UPDATE request when the Update Button is clicked', async () => {
        await openForEdition();

        // The loaded values first, or they land over the typed ones.
        await waitFor(() =>
          expect(getField('Name')).toHaveValue(`${resource} 1`)
        );

        await fillEveryField();

        await userEvent.click(getSubmitButton());

        const { request } = await waitForRequest('@update');

        expect(request.body).toEqual({
          alias: 'Alias abc',
          coordinates: 'Coordinates abc',
          name: 'Name abc'
        });

        // The modal closed on a success message.
        expect(
          await screen.findByText(
            labelResourceUpdated(capitalize(resourceType))
          )
        ).toBeVisible();
        await waitFor(() =>
          expect(
            screen.queryByText(`Modify a ${resourceType}`)
          ).not.toBeInTheDocument()
        );
      });
    });
  });
};
