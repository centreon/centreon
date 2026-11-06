import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { labelAddNewEntry } from '../../Hosts/translatedLabels';
import { labelSnmpVersion } from '../../ResourceForm/translatedLabels';
import initialize from './initialize';

const macrosTestId = 'host-template-form-check-options-macros';

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const queryMacroInput = (
  index: number,
  key: 'name' | 'value'
): HTMLInputElement | null =>
  document.querySelector<HTMLInputElement>(
    `input[data-testid="${macrosTestId}-${index}-${key}"]`
  );

const findMacroInput = async (
  index: number,
  key: 'name' | 'value'
): Promise<HTMLInputElement> => {
  await waitFor(() => expect(queryMacroInput(index, key)).not.toBeNull());

  return queryMacroInput(index, key) as HTMLInputElement;
};

const getAddMacroButton = async (): Promise<HTMLElement> =>
  within(await screen.findByTestId(macrosTestId)).getByRole('button', {
    name: labelAddNewEntry
  });

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper: the listing behind the panel shows the same names.
const pickOption = async (option: string): Promise<void> => {
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

const save = async (): Promise<void> => {
  const button = document.querySelector(
    `button[data-testid="${panelDataTestIds.save}"]`
  ) as HTMLButtonElement;

  await waitFor(() => expect(button).toBeEnabled());
  await userEvent.click(button);
};

const openCreationForm = async (): Promise<void> => {
  initialize();

  await waitForRequest('@getAllHostTemplates');
  await screen.findByText('generic-active-host-custom');

  await userEvent.click(await screen.findByTestId('add-resource'));

  await userEvent.type(await getInput('host-template-form-name'), 'linux');
  await userEvent.type(await getInput('host-template-form-alias'), 'Linux');
};

const openTemplate = async (id: number): Promise<void> => {
  initialize({ search: `?mode=edit&id=${id}` });

  await waitForRequest(`@getHostTemplate${id}`);
};

export default () => {
  describe('Host monitoring properties: ', () => {
    it('opens a template on its check command, arguments, SNMP version and macros', async () => {
      await openTemplate(1);

      await waitFor(() =>
        expect(
          screen.getByTestId('host-template-form-check-options-command')
        ).toHaveValue('check-host-alive')
      );
      expect(
        await getInput('host-template-form-check-options-args')
      ).toHaveValue('!3!80%');
      expect(screen.getByLabelText(labelSnmpVersion)).toHaveValue('2c');
      // Write-only: never read back.
      expect(await getInput('host-template-form-snmp-community')).toHaveValue(
        ''
      );
      expect(await findMacroInput(0, 'name')).toHaveValue('SNMPPORT');
      expect(await findMacroInput(0, 'value')).toHaveValue('161');
      expect(await findMacroInput(1, 'name')).toHaveValue('WARNING');
    });

    it('creates a template with its check command, SNMP settings and macros', async () => {
      await openCreationForm();

      await userEvent.click(
        screen.getByTestId('host-template-form-check-options-command')
      );
      const { request: commandsRequest } =
        await waitForRequest('@getFormCommands');

      // The host template route, which host write access does not gate.
      expect(commandsRequest.url.pathname).toContain(
        '/api/configuration/host_templates/commands'
      );
      await pickOption('check-host-alive');
      await userEvent.type(
        await getInput('host-template-form-check-options-args'),
        '!3!80%'
      );

      await userEvent.type(
        await getInput('host-template-form-snmp-community'),
        'public'
      );
      await userEvent.click(screen.getByLabelText(labelSnmpVersion));
      await pickOption('2c');

      await userEvent.click(await getAddMacroButton());
      await userEvent.type(await findMacroInput(0, 'name'), ' snmpport ');
      await userEvent.type(await findMacroInput(0, 'value'), '161');

      await save();

      const { request } = await waitForRequest('@createHostTemplate');
      const body = request.body as Record<string, unknown>;

      // Macro names as the server stores them: trimmed, upper-cased.
      expect(body.check_options).toEqual({
        args: ['3', '80%'],
        command_id: 9,
        macros: [
          {
            id: null,
            is_password: false,
            name: 'SNMPPORT',
            parent: null,
            value: '161'
          }
        ]
      });
      expect(body.snmp_community).toBe('public');
      expect(body.snmp_version).toBe('2c');
    });

    it('creates a template with none of them, the SNMP community left out', async () => {
      await openCreationForm();

      await save();

      const { request } = await waitForRequest('@createHostTemplate');
      const body = request.body as Record<string, unknown>;

      expect(body.check_options).toEqual({
        args: [],
        command_id: null,
        macros: []
      });
      expect(body.snmp_version).toBeNull();
      expect(body).not.toHaveProperty('snmp_community');
    });

    it('takes arguments without a check command and sends them with none', async () => {
      await openCreationForm();

      const args = await getInput('host-template-form-check-options-args');

      expect(args).toBeEnabled();
      await userEvent.type(args, '!80!90');

      await save();

      const { request } = await waitForRequest('@createHostTemplate');

      expect(
        (request.body as Record<string, unknown>).check_options
      ).toMatchObject({ args: ['80', '90'], command_id: null });
    });

    it('keeps the arguments of a template without a check command on save', async () => {
      await openTemplate(2);

      const args = await getInput('host-template-form-check-options-args');

      await waitFor(() => expect(args).toHaveValue('!80!graceful'));
      expect(args).toBeEnabled();
      expect(
        screen.getByTestId('host-template-form-check-options-command')
      ).toHaveValue('');

      const alias = await getInput('host-template-form-alias');

      await userEvent.type(alias, 'Generic host');

      await save();

      const { request } = await waitForRequest('@updateHostTemplate2');

      expect(
        (request.body as Record<string, unknown>).check_options
      ).toMatchObject({ args: ['80', 'graceful'], command_id: null });
    });

    it('sends only the macros the template owns or changes', async () => {
      await openTemplate(1);

      const ownValue = await findMacroInput(0, 'value');

      await waitFor(() => expect(ownValue).toHaveValue('161'));
      await userEvent.clear(ownValue);
      await userEvent.type(ownValue, '1161');

      await save();

      const { request } = await waitForRequest('@updateHostTemplate1');

      // The command's macro is left inherited: not sent.
      expect(
        (request.body as { check_options: { macros: unknown } }).check_options
          .macros
      ).toEqual([
        {
          id: 1,
          is_password: false,
          name: 'SNMPPORT',
          parent: null,
          value: '1161'
        }
      ]);
    });

    it('opens a template with its check command, SNMP and macros read-only for a user who may only look at templates', async () => {
      initialize({ hasWriteAccess: false, search: '?mode=edit&id=1' });

      await waitForRequest('@getHostTemplate1');

      const command = await screen.findByTestId(
        'host-template-form-check-options-command'
      );

      await waitFor(() => expect(command).toHaveValue('check-host-alive'));
      expect(command).toBeDisabled();
      // Template 1 has a command: only read-only keeps its arguments disabled.
      expect(
        await getInput('host-template-form-check-options-args')
      ).toBeDisabled();
      expect(
        await getInput('host-template-form-snmp-community')
      ).toBeDisabled();
      expect(screen.getByLabelText(labelSnmpVersion)).toBeDisabled();
      expect(await getAddMacroButton()).toBeDisabled();
      expect(await findMacroInput(0, 'name')).toBeDisabled();
      expect(await findMacroInput(0, 'value')).toBeDisabled();
    });

    it('opens a locked template with its check command, SNMP and macros read-only', async () => {
      await openTemplate(3);

      await waitFor(() =>
        expect(
          screen.getByTestId('host-template-form-check-options-command')
        ).toBeDisabled()
      );
      expect(
        await getInput('host-template-form-snmp-community')
      ).toBeDisabled();
      expect(screen.getByLabelText(labelSnmpVersion)).toBeDisabled();
      expect(await getAddMacroButton()).toBeDisabled();
    });
  });
};
