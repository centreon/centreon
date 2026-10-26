import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  labelAddCommand,
  labelAdditionalInformation,
  labelCommandLine,
  labelComments,
  labelGeneralInformation,
  labelInstalledPlugins,
  labelModifyCommand,
  labelName,
  labelPollerGlobalMacros,
  labelRequired,
  labelSave,
  labelSelectOptimizationConnector,
  labelStandardMacros
} from '../translatedLabels';
import initialize from './initialize';

const clickOutideTheField = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId('Modal-header'));
};

// The text fields put the test id on their wrapper and on the input.
const getField = (testId: string): HTMLInputElement =>
  screen.getAllByTestId(testId)[1] as HTMLInputElement;

const getCheckbox = (name: string): HTMLInputElement =>
  document.querySelector(`input[name="${name}"]`) as HTMLInputElement;

const getShellSyntaxSwitch = (): HTMLInputElement =>
  screen
    .getByTestId('enable-shell-syntax')
    .querySelector('input') as HTMLInputElement;

const getConnectorField = (): HTMLInputElement =>
  screen.getByTestId(labelSelectOptimizationConnector) as HTMLInputElement;

const getSubmitButton = (): HTMLButtonElement =>
  document.querySelector('button[data-testid="submit"]') as HTMLButtonElement;

const getButtonByText = (text: string): HTMLElement =>
  screen.getByRole('button', { name: text });

const commandLine =
  '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$ -v $_HOSTSNMPVERSION$ -C $_HOSTSNMPCOMMUNITY$ -n -p $ARG1$ -w $ARG2$ -c $ARG3$';

// The listing is displayed: the page has applied the first answer.
const initializeAndWaitForListing = async (): Promise<void> => {
  initialize();

  await waitForRequest('@getCommands');

  await screen.findByText('check_host_alive');
};

const openCreationModal = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId('add-resource'));

  expect(await screen.findByText(labelAddCommand)).toBeVisible();
};

// The edition form is filled once the command details are applied.
const openEditionModal = async (): Promise<void> => {
  await userEvent.click(screen.getByText('check_host_alive'));

  expect(await screen.findByText(labelModifyCommand)).toBeVisible();

  await waitFor(() =>
    expect(getField(labelName)).toHaveValue('check_host_alive')
  );
};

// What the form shows in both modes, from the snapshots.
const expectFormSections = (): void => {
  [
    labelGeneralInformation,
    labelAdditionalInformation,
    labelPollerGlobalMacros,
    labelInstalledPlugins,
    labelStandardMacros
  ].forEach((label) => {
    expect(screen.getAllByText(label)[0]).toBeVisible();
  });
  expect(getButtonByText('Cancel')).toBeEnabled();
};

const expectSubmitButton = async (enabled: boolean): Promise<void> => {
  await waitFor(() => {
    expect(getSubmitButton()).toHaveTextContent(new RegExp(`^${labelSave}$`));

    if (enabled) {
      expect(getSubmitButton()).toBeEnabled();

      return;
    }

    expect(getSubmitButton()).toBeDisabled();
  });
};

const closeModal = async (): Promise<void> => {
  await userEvent.click(screen.getByLabelText('close'));
};

const confirmLeave = async (label: 'Leave' | 'Discard'): Promise<void> => {
  await userEvent.click(await screen.findByLabelText(label));

  await waitFor(() =>
    expect(screen.queryByTestId('Modal-header')).not.toBeInTheDocument()
  );
};

export default (): void => {
  describe('Modal', () => {
    it('displays form fields with default values when the Modal is opened in Creation Mode', async () => {
      await initializeAndWaitForListing();

      await openCreationModal();

      expect(getField(labelName)).toBeVisible();
      expect(getField(labelName)).toHaveValue('');

      expect(getCheckbox('Notification')).not.toBeChecked();
      expect(getCheckbox('Check ')).not.toBeChecked();
      expect(getCheckbox('Discovery')).not.toBeChecked();
      expect(getCheckbox('Miscellaneous')).not.toBeChecked();

      expect(getField(labelCommandLine)).toBeEmptyDOMElement();
      expect(getField(labelCommandLine)).toHaveValue('');
      expect(getShellSyntaxSwitch()).not.toBeChecked();

      expect(getConnectorField()).toHaveValue('');
      expect(getField(labelComments)).toBeEmptyDOMElement();
      expect(getField(labelComments)).toHaveValue('');

      // From the snapshot.
      expectFormSections();
      await expectSubmitButton(false);

      await closeModal();
    });
    it('displays form fields with the selected command values when the Modal is opened in Edition Mode', async () => {
      await initializeAndWaitForListing();

      await openEditionModal();

      expect(getField(labelName)).toBeVisible();
      expect(getField(labelName)).toHaveValue('check_host_alive');

      expect(getCheckbox('Notification')).toBeChecked();
      expect(getCheckbox('Check ')).not.toBeChecked();
      expect(getCheckbox('Discovery')).not.toBeChecked();
      expect(getCheckbox('Miscellaneous')).not.toBeChecked();

      expect(getField(labelCommandLine)).toHaveValue(commandLine);

      expect(getShellSyntaxSwitch()).toBeChecked();
      await waitFor(() => expect(getConnectorField()).toHaveValue('Telegraf'));

      expect(getField(labelComments)).toHaveValue('some comment');

      // From the snapshot.
      expectFormSections();
      await expectSubmitButton(false);

      await closeModal();
    });

    it('disables the save button when no change has been made to the modal form', async () => {
      await initializeAndWaitForListing();

      await openEditionModal();

      await expectSubmitButton(false);

      // From the snapshot: the form can still be left.
      expect(getButtonByText('Cancel')).toBeEnabled();

      await closeModal();
    });
    it('disables the save button when there is error(s) in the form', async () => {
      await initializeAndWaitForListing();

      await openEditionModal();

      await userEvent.clear(getField(labelName));

      await expectSubmitButton(false);

      // From the snapshot: the other values are kept.
      expect(getField(labelName)).toHaveValue('');
      expect(getField(labelCommandLine)).toHaveValue(commandLine);

      await closeModal();

      await confirmLeave('Leave');
    });
    it('enables the create button when all mandatory fields are filled', async () => {
      await initializeAndWaitForListing();

      await openCreationModal();

      await expectSubmitButton(false);

      await userEvent.type(getField(labelName), 'New name');

      await userEvent.click(getCheckbox('Notification'));

      await userEvent.type(
        getField(labelCommandLine),
        '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$'
      );

      await expectSubmitButton(true);

      // From the snapshot: the values entered, the optional fields left empty.
      expect(getField(labelName)).toHaveValue('New name');
      expect(getCheckbox('Notification')).toBeChecked();
      expect(getField(labelCommandLine)).toHaveValue(
        '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$'
      );
      expect(getShellSyntaxSwitch()).not.toBeChecked();
      expect(getConnectorField()).toHaveValue('');
      expect(getField(labelComments)).toHaveValue('');

      await closeModal();
      await confirmLeave('Discard');
    });

    describe('Form validation', () => {
      it('validates that the name field is required', async () => {
        await initializeAndWaitForListing();

        await openEditionModal();

        await userEvent.clear(getField(labelName));

        await clickOutideTheField();

        expect(await screen.findByText(labelRequired)).toBeVisible();

        await closeModal();
        await confirmLeave('Leave');
      });

      it('validates that the command line field is required', async () => {
        await initializeAndWaitForListing();
        await openEditionModal();

        await userEvent.clear(getField(labelCommandLine));
        await clickOutideTheField();

        expect(await screen.findByText(labelRequired)).toBeVisible();

        await closeModal();
        await confirmLeave('Leave');
      });

      it('validates that the connector field is not required', async () => {
        await initializeAndWaitForListing();
        await openEditionModal();
        await waitFor(() =>
          expect(getConnectorField()).toHaveValue('Telegraf')
        );

        await userEvent.clear(getConnectorField());

        await clickOutideTheField();

        // Once the change is applied: the form can be saved.
        await expectSubmitButton(true);

        expect(screen.queryByText(labelRequired)).not.toBeInTheDocument();

        await closeModal();
        await confirmLeave('Discard');
      });

      it('validates that comment field is not required', async () => {
        await initializeAndWaitForListing();
        await openEditionModal();

        await userEvent.clear(getField(labelComments));

        await clickOutideTheField();

        // Once the change is applied: the form can be saved.
        await expectSubmitButton(true);

        expect(screen.queryByText(labelRequired)).not.toBeInTheDocument();

        await closeModal();
        await confirmLeave('Discard');
      });
    });

    describe('API requests', () => {
      it('sends a Post request when the Modal is in "Creation Mode" and the Create Button is clicked', async () => {
        await initializeAndWaitForListing();
        await openCreationModal();

        await expectSubmitButton(false);

        await userEvent.type(getField(labelName), 'New name');

        await userEvent.click(getCheckbox('Discovery'));

        await userEvent.type(
          getField(labelCommandLine),
          '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$'
        );

        await userEvent.click(getShellSyntaxSwitch());

        await userEvent.click(getConnectorField());

        await userEvent.click(
          await screen.findByRole('option', { name: 'Telegraf' })
        );

        await userEvent.type(getField(labelComments), 'some comment');

        await userEvent.click(getSubmitButton());

        expect(await screen.findByText('Command created')).toBeVisible();

        const { request } = await waitForRequest('@createCommand');

        expect(request.body).toEqual({
          command_line: '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$',
          comment: 'some comment',
          connector: '/centreon/api/latest/configuration/connectors/4',
          is_shell_enabled: true,
          name: 'New name',
          type: 'Discovery'
        });

        // From the snapshot: the modal is closed, back to the listing.
        await waitFor(() =>
          expect(screen.queryByText(labelAddCommand)).not.toBeInTheDocument()
        );
        expect(screen.getByText('check_host_alive')).toBeVisible();
      });
      it('sends a PATCH request when the Modal is in "Edition Mode" and the Update Button is clicked.', async () => {
        await initializeAndWaitForListing();
        await openEditionModal();

        await userEvent.clear(getField(labelName));
        await userEvent.type(getField(labelName), 'Updated name');

        await userEvent.click(getCheckbox('Discovery'));

        await userEvent.click(getShellSyntaxSwitch());

        await userEvent.click(getSubmitButton());

        const { request } = await waitForRequest('@updateCommand');

        expect(request.body).toEqual({
          command_line: commandLine,
          comment: 'some comment',
          connector:
            '/centreon/api/latest/.well-known/genid/7b9eb72f31977bee3cf4',
          is_shell_enabled: false,
          name: 'Updated name',
          type: 'Discovery'
        });

        expect(await screen.findByText('Command updated')).toBeVisible();

        // From the snapshot: the modal is closed, back to the listing.
        await waitFor(() =>
          expect(screen.queryByText(labelModifyCommand)).not.toBeInTheDocument()
        );
        expect(screen.getByText('check_host_alive')).toBeVisible();
      });
    });

    describe('Ask Before quit popup', () => {
      it('displays a modal when the form is updated with errors and the cancel button is clicked', async () => {
        await initializeAndWaitForListing();
        await openEditionModal();

        await userEvent.clear(getField(labelName));

        await userEvent.click(getButtonByText('Cancel'));

        expect(
          await screen.findByText('Do you want to leave this page?')
        ).toBeVisible();

        // From the snapshot: what the popup says and offers, and the error
        // shown on the form behind it.
        const dialog = (await screen.findAllByRole('dialog')).at(
          -1
        ) as HTMLElement;

        expect(
          within(dialog).getByText(
            'Your changes will not be saved if you leave this page.'
          )
        ).toBeVisible();
        expect(within(dialog).getByLabelText('Stay')).toBeVisible();
        expect(within(dialog).getByLabelText('Leave')).toBeVisible();
        expect(screen.getByText(labelRequired)).toBeVisible();

        await confirmLeave('Leave');
      });

      it('displays a modal when the form is updated and the cancel button is clicked', async () => {
        await initializeAndWaitForListing();
        await openEditionModal();

        await userEvent.type(getField(labelName), 'New name');
        await userEvent.click(getButtonByText('Cancel'));

        expect(
          await screen.findByText('Do you want to save the changes?')
        ).toBeVisible();

        // From the snapshot: what the popup says and offers.
        const dialog = (await screen.findAllByRole('dialog')).at(
          -1
        ) as HTMLElement;

        expect(
          within(dialog).getByText(
            'If you click on Discard, your changes will not be saved.'
          )
        ).toBeVisible();
        expect(within(dialog).getByLabelText('Discard')).toBeVisible();
        expect(
          within(dialog).getByRole('button', { name: labelSave })
        ).toBeVisible();
        expect(getField(labelName)).toHaveValue('check_host_aliveNew name');

        await confirmLeave('Discard');
      });
    });
  });
};
