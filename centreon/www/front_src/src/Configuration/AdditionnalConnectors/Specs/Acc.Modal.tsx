import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { equals, keys } from 'ramda';

import { ParameterKeys } from '../models';
import {
  labelAddAnAdditionalConfiguration,
  labelAdditionalConnectorCreated,
  labelAdditionalConnectorUpdated,
  labelAddvCenterESX,
  labelAteastOnePollerIsRequired,
  labelCancel,
  labelDescription,
  labelInvalidPortNumber,
  labelModifyConnectorConfiguration,
  labelMustBeAvalidURL,
  labelName,
  labelNameMustBeAtLeast,
  labelRemoveVCenterESX,
  labelRequired,
  labelSave,
  labelSelectPollers,
  labelType,
  labelVcenterNameMustBeUnique,
  labelvCenterESX
} from '../translatedLabels';
import { maskedPassword } from '../utils';
import initialize from './initialize';

export const defaultParameters = {
  [ParameterKeys.name]: 'my_vcenter',
  [ParameterKeys.url]: 'https://<ip_hostname>/sdk',
  [ParameterKeys.username]: '',
  [ParameterKeys.password]: ''
};

const vcenters = [
  {
    [ParameterKeys.name]: 'vCenter1',
    [ParameterKeys.password]: 'password1',
    [ParameterKeys.url]: 'https://vcenter1.example.com/sdk',
    [ParameterKeys.username]: 'user1'
  },
  {
    [ParameterKeys.name]: 'vCenter2',
    [ParameterKeys.password]: 'password2',
    [ParameterKeys.url]: '192.0.0.1',
    [ParameterKeys.username]: 'user2'
  }
];

const clickOutideTheField = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId('Modal-header'));
};

const openCreationModal = async (): Promise<void> => {
  await waitForRequest('@getConnectors');
  await userEvent.click(await screen.findByTestId('add-resource'));
};

const openEditionModal = async (): Promise<void> => {
  await waitForRequest('@getConnectors');
  await userEvent.click(await screen.findByText('VMWare1'));
};

// The text field and its input share the test id: the input.
const getNameInput = async (): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(labelName)).find(
    (element) => element.tagName === 'INPUT'
  ) as HTMLInputElement;

const getParameterInputs = (parameter: string): Array<HTMLInputElement> =>
  Array.from(
    document.querySelectorAll<HTMLInputElement>(
      `input[data-testid="${parameter}_value"]`
    )
  );

const getPortInput = (): HTMLInputElement =>
  document.querySelector('input[name="port"]') as HTMLInputElement;

const getSubmitButton = (): HTMLButtonElement =>
  document.querySelector('button[data-testid="submit"]') as HTMLButtonElement;

const getCancelButton = (): HTMLButtonElement =>
  document.querySelector('button[data-testid="cancel"]') as HTMLButtonElement;

const scrollModalToBottom = (): void => {
  const body = screen.getByTestId('modal-body');

  body.scrollTop = body.scrollHeight;
};

// What the validation snapshots showed: the message is under that field, and
// marks it as invalid.
const expectErrorOn = async (
  input: HTMLElement,
  message: string
): Promise<void> => {
  const field = input.closest('.MuiFormControl-root') as HTMLElement;

  expect(await within(field).findByText(message)).toBeVisible();
  expect(input).toHaveAttribute('aria-invalid', 'true');
};

const closeModal = async (): Promise<void> => {
  await userEvent.click(screen.getAllByLabelText('close')[0]);
};

const fillMandatoryFields = async (): Promise<void> => {
  await userEvent.type(await getNameInput(), 'New name');

  await userEvent.click(screen.getByTestId(labelSelectPollers));

  await userEvent.click(await screen.findByText('poller1'));

  await userEvent.click(screen.getByTestId(labelSelectPollers));

  const [url] = getParameterInputs('URL');

  await userEvent.clear(url);
  await userEvent.type(url, 'http://10.10.10.10/sdk');

  await userEvent.type(getParameterInputs('Username')[0], 'username');
  await userEvent.type(getParameterInputs('Password')[0], 'password');
};

export default (): void => {
  describe('Modal', () => {
    beforeEach(() => {
      initialize();
    });

    it('displays form fields with default values when the Modal is opened in Creation Mode', async () => {
      await openCreationModal();

      expect(
        await screen.findByText(labelAddAnAdditionalConfiguration)
      ).toBeVisible();

      const nameInput = await getNameInput();

      expect(nameInput).toBeVisible();
      expect(nameInput).toHaveValue('');

      expect(screen.getByLabelText(labelDescription)).toBeVisible();
      expect(screen.getByLabelText(labelDescription)).toHaveValue('');

      const type = screen.getByTestId(labelType).parentElement
        ?.firstElementChild as HTMLElement;

      expect(type).toBeVisible();
      expect(type).toHaveTextContent(/^VMWare 6\/7$/);

      expect(screen.getByTestId(labelSelectPollers)).toBeVisible();

      expect(screen.getByText(labelvCenterESX)).toBeVisible();
      expect(screen.getAllByTestId('parameterGroup')).toHaveLength(1);

      keys(defaultParameters).forEach((parameter) => {
        const [input] = getParameterInputs(parameter);

        expect(input).toBeVisible();
        expect(input).toHaveValue(defaultParameters[parameter]);
        expect(input).toBeEnabled();
      });

      expect(screen.getByText(labelAddvCenterESX)).toBeVisible();
      expect(screen.getByLabelText(labelAddvCenterESX)).toBeEnabled();

      getPortInput().scrollIntoView();
      expect(getPortInput()).toBeVisible();
      expect(getPortInput()).toHaveValue(5700);
      expect(getPortInput()).toBeEnabled();

      expect(getCancelButton()).toHaveTextContent(
        new RegExp(`^${labelCancel}$`)
      );
      expect(getCancelButton()).toBeEnabled();

      expect(getSubmitButton()).toHaveTextContent(new RegExp(`^${labelSave}$`));
      expect(getSubmitButton()).toBeDisabled();

      await closeModal();
    });
    it('displays form fields with the selected ACC values when the Modal is opened in Edition Mode', async () => {
      await openEditionModal();

      expect(
        await screen.findByText(labelModifyConnectorConfiguration)
      ).toBeVisible();

      const nameInput = await getNameInput();

      await waitFor(() => expect(nameInput).toHaveValue('VMWare1'));
      expect(nameInput).toBeVisible();

      expect(screen.getByLabelText(labelDescription)).toBeVisible();
      expect(screen.getByLabelText(labelDescription)).toHaveValue(
        'Description for VMWare1'
      );

      const type = screen.getByTestId(labelType).parentElement
        ?.firstElementChild as HTMLElement;

      expect(type).toBeVisible();
      expect(type).toHaveTextContent(/^VMWare 6\/7$/);

      expect(await screen.findByText('Poller1')).toBeVisible();
      expect(screen.getByText('Poller2')).toBeVisible();

      scrollModalToBottom();

      expect(screen.getByText(labelvCenterESX)).toBeVisible();
      expect(screen.getAllByTestId('parameterGroup')).toHaveLength(2);

      vcenters.forEach((vcenter, index) => {
        keys(vcenter).forEach((parameter) => {
          const input = getParameterInputs(parameter)[index];

          expect(input).toBeVisible();

          if (equals(parameter, 'Password')) {
            expect(input).toHaveValue(maskedPassword);
            expect(input).toBeDisabled();
          } else {
            expect(input).toHaveValue(vcenter[parameter]);
            expect(input).toBeEnabled();
          }
        });
      });

      // What the snapshot showed: each of the two vCenters can be removed.
      expect(screen.getAllByTestId(labelRemoveVCenterESX)).toHaveLength(2);

      expect(screen.getByText(labelAddvCenterESX)).toBeVisible();
      expect(screen.getByLabelText(labelAddvCenterESX)).toBeEnabled();

      getPortInput().scrollIntoView();
      expect(getPortInput()).toBeVisible();
      expect(getPortInput()).toHaveValue(443);
      expect(getPortInput()).toBeEnabled();

      expect(getCancelButton()).toHaveTextContent(
        new RegExp(`^${labelCancel}$`)
      );
      expect(getCancelButton()).toBeEnabled();

      expect(getSubmitButton()).toHaveTextContent(new RegExp(`^${labelSave}$`));
      expect(getSubmitButton()).toBeDisabled();

      await closeModal();
    });

    it('disables the save button when no change has been made to the modal form', async () => {
      await openEditionModal();

      expect(
        await screen.findByText(labelModifyConnectorConfiguration)
      ).toBeVisible();

      // The connector is loaded.
      await waitFor(async () =>
        expect(await getNameInput()).toHaveValue('VMWare1')
      );

      scrollModalToBottom();

      expect(getSubmitButton()).toHaveTextContent(new RegExp(`^${labelSave}$`));
      expect(getSubmitButton()).toBeDisabled();

      // What the snapshot showed: the form can still be cancelled.
      expect(getCancelButton()).toBeEnabled();

      await closeModal();
    });
    it('disables the save button when there is error(s) in the form', async () => {
      await openEditionModal();

      expect(
        await screen.findByText(labelModifyConnectorConfiguration)
      ).toBeVisible();

      const nameInput = await getNameInput();

      await waitFor(() => expect(nameInput).toHaveValue('VMWare1'));
      await userEvent.clear(nameInput);

      scrollModalToBottom();

      expect(getSubmitButton()).toHaveTextContent(new RegExp(`^${labelSave}$`));
      expect(getSubmitButton()).toBeDisabled();

      await closeModal();
      await userEvent.click(await screen.findByText('Leave'));
    });
    it('enables the create button when all mandatory fields are filled', async () => {
      await openCreationModal();

      expect(
        await screen.findByText(labelAddAnAdditionalConfiguration)
      ).toBeVisible();

      expect(getSubmitButton()).toHaveTextContent(new RegExp(`^${labelSave}$`));
      expect(getSubmitButton()).toBeDisabled();

      await fillMandatoryFields();

      expect(getSubmitButton()).toHaveTextContent(new RegExp(`^${labelSave}$`));
      await waitFor(() => expect(getSubmitButton()).toBeEnabled());

      // What the snapshot showed: the selected poller, and the password
      // typed in a masked field.
      expect(
        within(
          screen.getByTestId(labelSelectPollers).parentElement as HTMLElement
        ).getByText('poller1')
      ).toBeVisible();
      expect(getParameterInputs('Password')[0]).toHaveAttribute(
        'type',
        'password'
      );

      await closeModal();
      await userEvent.click(await screen.findByLabelText('Discard'));
    });

    it('hides Delete Parameter Group buttons when there is only one parameter group', async () => {
      await openCreationModal();

      // The form is displayed, with its single group.
      expect(await screen.findAllByTestId('parameterGroup')).toHaveLength(1);
      expect(
        screen.queryByTestId(labelRemoveVCenterESX)
      ).not.toBeInTheDocument();

      await closeModal();
    });

    it(`adds a new parameter group when "Add vCenter ESX" button is clicked`, async () => {
      await openCreationModal();

      expect(await screen.findAllByTestId('parameterGroup')).toHaveLength(1);

      await userEvent.click(screen.getByText(labelAddvCenterESX));

      scrollModalToBottom();

      await waitFor(() =>
        expect(screen.getAllByTestId('parameterGroup')).toHaveLength(2)
      );

      // What the snapshot showed: the new group comes with its own default
      // name, the default URL and no credentials; each group can be removed.
      expect(getParameterInputs('vCenter name')[1]).toHaveValue('my_vcenter_1');
      expect(getParameterInputs('URL')[1]).toHaveValue(
        'https://<ip_hostname>/sdk'
      );
      expect(getParameterInputs('Username')[1]).toHaveValue('');
      expect(getParameterInputs('Password')[1]).toHaveValue('');
      expect(screen.getAllByTestId(labelRemoveVCenterESX)).toHaveLength(2);
      expect(getSubmitButton()).toBeDisabled();

      await closeModal();
      await userEvent.click(await screen.findByLabelText('Leave'));
    });
    it(`removes a parameter group when the ${labelRemoveVCenterESX} button is clicked`, async () => {
      await openCreationModal();

      await userEvent.click(await screen.findByText(labelAddvCenterESX));

      scrollModalToBottom();

      expect(await screen.findAllByTestId(labelRemoveVCenterESX)).toHaveLength(
        2
      );

      expect(screen.getAllByTestId('parameterGroup')).toHaveLength(2);

      await userEvent.click(screen.getAllByTestId(labelRemoveVCenterESX)[0]);

      await waitFor(() =>
        expect(screen.getAllByTestId('parameterGroup')).toHaveLength(1)
      );

      await closeModal();
      await userEvent.click(await screen.findByLabelText('Leave'));
    });

    describe('Form validation', () => {
      it('validates that the name field is required', async () => {
        await openCreationModal();

        const nameInput = await getNameInput();

        await userEvent.clear(nameInput);

        await clickOutideTheField();

        expect(await screen.findByText(labelRequired)).toBeVisible();
        await expectErrorOn(nameInput, labelRequired);

        await closeModal();
      });

      it('validates that at least one poller is required', async () => {
        await openCreationModal();

        await userEvent.click(await screen.findByTestId(labelSelectPollers));

        await userEvent.click(await screen.findByText('poller1'));
        await clickOutideTheField();

        await userEvent.click(await screen.findByTestId('CancelIcon'));

        expect(
          await screen.findByText(labelAteastOnePollerIsRequired)
        ).toBeVisible();

        // What the snapshot showed: the poller is removed, the error is on
        // the pollers field.
        await expectErrorOn(
          screen.getByTestId(labelSelectPollers),
          labelAteastOnePollerIsRequired
        );
        expect(screen.queryByText('poller1')).not.toBeInTheDocument();

        await closeModal();
      });

      it('validates that vCenter name field is required', async () => {
        await openCreationModal();

        await screen.findAllByTestId('parameterGroup');
        const [vCenterName] = getParameterInputs('vCenter name');

        await userEvent.clear(vCenterName);

        await clickOutideTheField();

        expect(await screen.findByText(labelRequired)).toBeVisible();
        await expectErrorOn(vCenterName, labelRequired);

        await closeModal();
        await userEvent.click(await screen.findByText('Leave'));
      });

      it('validates that vCenter name must be unique for its own ACC', async () => {
        await openCreationModal();

        await userEvent.click(await screen.findByText(labelAddvCenterESX));

        await waitFor(() =>
          expect(getParameterInputs('vCenter name')).toHaveLength(2)
        );

        const [first, second] = getParameterInputs('vCenter name');

        await userEvent.clear(first);
        await userEvent.type(first, 'vcenter1');

        await userEvent.clear(second);
        await userEvent.type(second, 'vcenter1');

        await clickOutideTheField();

        expect(
          (await screen.findAllByText(labelVcenterNameMustBeUnique))[0]
        ).toBeVisible();

        // What the snapshot showed: both names are flagged.
        await expectErrorOn(first, labelVcenterNameMustBeUnique);
        await expectErrorOn(second, labelVcenterNameMustBeUnique);

        await closeModal();
        await userEvent.click(await screen.findByText('Leave'));
      });

      it('validates that vCenter URL field is required', async () => {
        await openCreationModal();

        await screen.findAllByTestId('parameterGroup');
        const [url] = getParameterInputs('URL');

        await userEvent.clear(url);

        await clickOutideTheField();

        expect(await screen.findByText(labelRequired)).toBeVisible();
        await expectErrorOn(url, labelRequired);

        await closeModal();
        await userEvent.click(await screen.findByText('Leave'));
      });

      it('validates that vCenter username is required in Creation Mode', async () => {
        await openCreationModal();

        await screen.findAllByTestId('parameterGroup');
        const [username] = getParameterInputs('Username');

        await userEvent.clear(username);

        await clickOutideTheField();

        expect(await screen.findByText(labelRequired)).toBeVisible();
        await expectErrorOn(username, labelRequired);

        await closeModal();
      });

      it('validates that vCenter username is required Edition Mode', async () => {
        await openEditionModal();

        await waitFor(() =>
          expect(getParameterInputs('Username')[0]).toHaveValue('user1')
        );
        const [username] = getParameterInputs('Username');

        await userEvent.clear(username);

        await clickOutideTheField();

        expect(await screen.findByText(labelRequired)).toBeVisible();
        // What the snapshot showed: only the cleared username is flagged.
        await expectErrorOn(username, labelRequired);
        expect(screen.getAllByText(labelRequired)).toHaveLength(1);

        await closeModal();
        await userEvent.click(await screen.findByLabelText('Leave'));
      });

      it('validates that vCenter password field is required in Creation Mode', async () => {
        await openCreationModal();

        await screen.findAllByTestId('parameterGroup');
        const [password] = getParameterInputs('Password');

        await userEvent.clear(password);

        await clickOutideTheField();

        expect(await screen.findByText(labelRequired)).toBeVisible();
        await expectErrorOn(password, labelRequired);

        await closeModal();
      });

      it('validates that vCenter password field is disabled and masked in Edition Mode', async () => {
        await openEditionModal();

        await waitFor(() =>
          expect(getParameterInputs('Password')[0]).toBeDisabled()
        );

        await waitFor(() =>
          expect(getParameterInputs('Password')[0]).toHaveValue(maskedPassword)
        );

        await clickOutideTheField();

        // Something the same validation shows: a name too short, so the
        // check below runs once the form has validated.
        const nameInput = await getNameInput();

        await userEvent.clear(nameInput);
        await userEvent.type(nameInput, 'ab');
        await clickOutideTheField();
        expect(await screen.findByText(labelNameMustBeAtLeast)).toBeVisible();

        expect(screen.queryByText(labelRequired)).not.toBeInTheDocument();

        await closeModal();
      });

      it('validates that port field is required', async () => {
        await openCreationModal();

        await screen.findAllByTestId('parameterGroup');

        await userEvent.clear(getPortInput());
        getPortInput().blur();

        expect(await screen.findByText(labelInvalidPortNumber)).toBeVisible();
        await expectErrorOn(getPortInput(), labelInvalidPortNumber);
        // What the snapshot showed: the cleared port falls back to 0.
        expect(getPortInput()).toHaveValue(0);

        await closeModal();
        await userEvent.click(await screen.findByLabelText('Leave'));
      });

      it('validates that name length must be between 3 and 50 characters', async () => {
        await openCreationModal();

        const nameInput = await getNameInput();

        await userEvent.clear(nameInput);
        await userEvent.type(nameInput, 'ab');

        await clickOutideTheField();

        expect(await screen.findByText(labelNameMustBeAtLeast)).toBeVisible();
        await expectErrorOn(nameInput, labelNameMustBeAtLeast);

        await closeModal();
        await userEvent.click(await screen.findByLabelText('Leave'));
      });

      it('validates that the description field is not required', async () => {
        await openCreationModal();

        await userEvent.clear(await screen.findByLabelText(labelDescription));

        await clickOutideTheField();

        // Something the same validation shows: a name too short, so the
        // check below runs once the form has validated.
        await userEvent.type(await getNameInput(), 'ab');
        await clickOutideTheField();
        expect(await screen.findByText(labelNameMustBeAtLeast)).toBeVisible();

        expect(screen.queryByText(labelRequired)).not.toBeInTheDocument();

        await closeModal();
      });

      it('validates that the port should be a valid integer', async () => {
        await openCreationModal();

        await screen.findAllByTestId('parameterGroup');

        // Cleared, the field shows the fallback 0; typed after it, '0.1'
        // would be clamped to the minimum port. Replacing the content, as a
        // user selecting it does, leaves a decimal number.
        await userEvent.tripleClick(getPortInput());
        await userEvent.keyboard('0.1');
        expect(Number.isInteger(Number(getPortInput().value))).toBe(false);

        await clickOutideTheField();

        expect(await screen.findByText(labelInvalidPortNumber)).toBeVisible();
        await expectErrorOn(getPortInput(), labelInvalidPortNumber);

        await closeModal();
        await userEvent.click(await screen.findByLabelText('Leave'));
      });

      it('validates that the port should be between 0 and 65535', async () => {
        await openCreationModal();

        await screen.findAllByTestId('parameterGroup');

        await userEvent.clear(getPortInput());
        await userEvent.type(getPortInput(), '70000');

        await clickOutideTheField();

        expect(await screen.findByText(labelInvalidPortNumber)).toBeVisible();
        await expectErrorOn(getPortInput(), labelInvalidPortNumber);

        await closeModal();
        await userEvent.click(await screen.findByLabelText('Leave'));
      });

      it('validates that vcenter url must be a valid URL or an IP address', async () => {
        await openCreationModal();

        await screen.findAllByTestId('parameterGroup');
        const [url] = getParameterInputs('URL');

        for (const invalidUrl of ['abc', '170.600.12', 'http://exa_mple.com']) {
          await userEvent.clear(url);
          await userEvent.type(url, invalidUrl);

          await clickOutideTheField();

          expect(await screen.findByText(labelMustBeAvalidURL)).toBeVisible();
          await expectErrorOn(url, labelMustBeAvalidURL);
        }

        for (const validUrl of [
          '192.110.0.1/sdk',
          '170.12.12.1',
          'http://example.com'
        ]) {
          await userEvent.clear(url);
          // The emptied field is flagged as required: the next check waits
          // for the validation of the pasted value, a single change, to
          // replace that error.
          await expectErrorOn(url, labelRequired);

          url.focus();
          await userEvent.paste(validUrl);

          await clickOutideTheField();

          await waitFor(() =>
            expect(screen.queryByText(labelRequired)).not.toBeInTheDocument()
          );
          expect(
            screen.queryByText(labelMustBeAvalidURL)
          ).not.toBeInTheDocument();
          expect(url).toHaveAttribute('aria-invalid', 'false');
        }

        await closeModal();
        await userEvent.click(await screen.findByText('Leave'));
      });
    });

    describe('API requests', () => {
      it('sends a Post request when the Modal is in "Creation Mode" and the Create Button is clicked', async () => {
        await openCreationModal();

        expect(
          await screen.findByText(labelAddAnAdditionalConfiguration)
        ).toBeVisible();

        expect(getSubmitButton()).toHaveTextContent(
          new RegExp(`^${labelSave}$`)
        );
        expect(getSubmitButton()).toBeDisabled();

        await fillMandatoryFields();

        await waitFor(() => expect(getSubmitButton()).toBeEnabled());
        await userEvent.click(getSubmitButton());

        expect(
          await screen.findByText(labelAdditionalConnectorCreated)
        ).toBeInTheDocument();

        const { request } = await waitForRequest('@createConnector');

        expect(request.body).toEqual({
          description: null,
          name: 'New name',
          parameters: {
            port: 5700,
            vcenters: [
              {
                id: null,
                name: 'my_vcenter',
                password: 'password',
                url: 'http://10.10.10.10/sdk',
                username: 'username'
              }
            ]
          },
          pollers: [1],
          type: 'vmware_v6'
        });

        // What the snapshot showed: the modal is closed.
        await waitFor(() =>
          expect(
            screen.queryByText(labelAddAnAdditionalConfiguration)
          ).not.toBeInTheDocument()
        );
      });
      it('sends an Update request when the Modal is in "Edition Mode" and the Update Button is clicked.', async () => {
        await openEditionModal();

        expect(
          await screen.findByText(labelModifyConnectorConfiguration)
        ).toBeVisible();

        const nameInput = await getNameInput();

        await waitFor(() => expect(nameInput).toHaveValue('VMWare1'));
        await userEvent.clear(nameInput);
        await userEvent.type(nameInput, 'Updated name');
        await userEvent.clear(getPortInput());
        await userEvent.type(getPortInput(), '100');

        expect(getParameterInputs('Password')[0]).toBeDisabled();
        await userEvent.click(screen.getAllByTestId('EditIcon')[0]);
        await userEvent.type(getParameterInputs('Password')[0], 'new password');
        await userEvent.click(screen.getByTestId('CheckIcon'));
        expect(await screen.findByTestId('RestartAltIcon')).toBeVisible();

        await userEvent.click(getSubmitButton());

        const { request } = await waitForRequest('@updateConnector');

        expect(request.body).toEqual({
          description: 'Description for VMWare1',
          name: 'Updated name',
          parameters: {
            // The Cypress spec expected 1000 here, an artefact of how its
            // typing met the 0 the cleared field shows; typing 100 sends 100.
            port: 100,
            vcenters: [
              {
                id: 1,
                name: 'vCenter1',
                password: 'new password',
                url: 'https://vcenter1.example.com/sdk',
                username: 'user1'
              },
              {
                id: 2,
                name: 'vCenter2',
                password: null,
                url: '192.0.0.1',
                username: 'user2'
              }
            ]
          },
          pollers: [101, 102],
          type: 'vmware_v6'
        });

        expect(
          await screen.findByText(labelAdditionalConnectorUpdated)
        ).toBeInTheDocument();

        // What the snapshot showed: the modal is closed.
        await waitFor(() =>
          expect(
            screen.queryByText(labelModifyConnectorConfiguration)
          ).not.toBeInTheDocument()
        );
      });
    });

    describe('Ask Before quit popup', () => {
      it('displays a modal when the form is updated with errors and the cancel button is clicked', async () => {
        await openEditionModal();

        await waitFor(() =>
          expect(getParameterInputs('vCenter name')[0]).toHaveValue('vCenter1')
        );

        // The text field and its input share the test id: the input.
        const vCenterName = (
          await screen.findAllByTestId('vCenter name_value')
        )[1];

        await userEvent.clear(vCenterName);
        vCenterName.blur();
        await userEvent.click(screen.getByText(labelCancel));

        expect(
          await screen.findByText('Do you want to leave this page?')
        ).toBeVisible();

        // What the snapshot showed: the dialog explains the changes are
        // lost, and offers to stay or to leave.
        expect(
          screen.getByText(
            'Your changes will not be saved if you leave this page.'
          )
        ).toBeVisible();
        expect(screen.getByLabelText('Stay')).toBeVisible();
        expect(screen.getByLabelText('Leave')).toBeVisible();

        await userEvent.click(screen.getByLabelText('Leave'));
      });

      it('displays a modal when the form is updated and the cancel button is clicked', async () => {
        await openEditionModal();

        const nameInput = await getNameInput();

        await waitFor(() => expect(nameInput).toHaveValue('VMWare1'));
        await userEvent.type(nameInput, 'New name');
        await userEvent.click(screen.getByText(labelCancel));

        expect(
          await screen.findByText('Do you want to save the changes?')
        ).toBeVisible();

        // What the snapshot showed: the dialog offers to discard or to save.
        expect(
          screen.getByText(
            'If you click on Discard, your changes will not be saved.'
          )
        ).toBeVisible();
        expect(screen.getByLabelText('Discard')).toBeVisible();

        await userEvent.click(screen.getByLabelText('Discard'));
      });
    });
  });
};
