import { waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelAddNewEntry,
  labelAlreadyExists,
  labelRequired,
  labelReservedMacroName
} from '../../ConfigurationBase/translatedLabels';
import {
  labelArgumentsForbiddenTokens,
  labelFillAllArgumentsOrNone
} from '../translatedLabels';
import { fillRequiredServiceFields } from './formHelpers';
import initialize from './initialize';

const commandTestId = 'service-form-check-command';
const argsTestId = 'service-form-args';
const macrosTestId = 'service-form-macros';

const macroInputSelector = (index: number, key: 'name' | 'value'): string =>
  `input[data-testid="${macrosTestId}-${index}-${key}"]`;

const queryMacroInput = (
  index: number,
  key: 'name' | 'value'
): HTMLInputElement | null =>
  document.querySelector<HTMLInputElement>(macroInputSelector(index, key));

const findMacroInput = async (
  index: number,
  key: 'name' | 'value'
): Promise<HTMLInputElement> => {
  await waitFor(() => expect(queryMacroInput(index, key)).not.toBeNull());

  return queryMacroInput(index, key) as HTMLInputElement;
};

const queryArgs = (): HTMLInputElement | null =>
  document.querySelector<HTMLInputElement>(
    `input[data-testid="${argsTestId}"]`
  );

const findArgs = async (): Promise<HTMLInputElement> => {
  await waitFor(() => expect(queryArgs()).not.toBeNull());

  return queryArgs() as HTMLInputElement;
};

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper: the listing behind the panel shows other names.
const pickCommand = async (option: string): Promise<void> => {
  await userEvent.click(await screen.findByTestId(commandTestId));
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

const clearSelector = async (testId: string): Promise<void> => {
  const root = (await screen.findByTestId(testId)).closest(
    '.MuiAutocomplete-root'
  ) as HTMLElement;

  await userEvent.hover(root);
  await userEvent.click(
    root.querySelector('.MuiAutocomplete-clearIndicator') as HTMLElement
  );
};

const clearCommand = (): Promise<void> => clearSelector(commandTestId);

const addMacro = async (): Promise<void> => {
  await userEvent.click(
    within(await screen.findByTestId(macrosTestId)).getByRole('button', {
      name: labelAddNewEntry
    })
  );
};

const replaceValue = async (
  input: HTMLInputElement,
  value: string
): Promise<void> => {
  await userEvent.clear(input);
  await userEvent.type(input, value);
};

// Shown once the field is left.
const leave = (input: HTMLElement): void => {
  act(() => {
    input.focus();
    input.blur();
  });
};

const expectError = async (
  input: HTMLElement,
  message: string
): Promise<void> => {
  expect(
    await within(
      input.closest('.MuiFormControl-root') as HTMLElement
    ).findByText(message)
  ).toBeInTheDocument();
};

const getSaveButton = (): HTMLButtonElement =>
  document.querySelector(
    `button[data-testid="${panelDataTestIds.save}"]`
  ) as HTMLButtonElement;

const save = async (): Promise<void> => {
  await waitFor(() => expect(getSaveButton()).toBeEnabled());
  await userEvent.click(getSaveButton());
};

const openCreationForm = async (): Promise<void> => {
  initialize();

  await waitForRequest('@getAllServices');
  await screen.findByText('cpu');

  await userEvent.click(await screen.findByTestId('add-resource'));
  // Name and host are required; no template, these tests are about the
  // command it would bring.
  await fillRequiredServiceFields({ withTemplate: false });
};

const openService0 = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllServices');
  await userEvent.click(await screen.findByText('cpu'));
  await waitForRequest('@getService0');
};

export default () => {
  it('opens a service on its check command, arguments and macros', async () => {
    await openService0();

    await waitFor(() =>
      expect(screen.getByTestId(commandTestId)).toHaveValue(
        'check-centreon-cpu'
      )
    );
    await waitFor(async () => expect(await findArgs()).toHaveValue('!80!90'));

    await waitFor(() =>
      expect(queryMacroInput(0, 'name')).toHaveValue('WARNING')
    );
    expect(queryMacroInput(0, 'value')).toHaveValue('80');
    // Masked and locked: the API never sends a password back.
    await waitFor(() => {
      const input = queryMacroInput(1, 'value');

      expect(input).toHaveAttribute('type', 'password');
      expect(input).toBeDisabled();
    });
    expect(queryMacroInput(2, 'name')).toHaveValue('TIMEOUT');
    expect(queryMacroInput(2, 'value')).toHaveValue('10');
  });

  it('requires a check command without a template, then creates the service with it', async () => {
    await openCreationForm();

    const command = await screen.findByTestId(commandTestId);

    // Arguments go with a command.
    expect(queryArgs()).toBeNull();

    leave(command);
    await expectError(command, labelRequired);
    expect(getSaveButton()).toBeDisabled();

    await pickCommand('check-centreon-cpu');

    // Only active check commands, from the services' own selector.
    const { request: commandsRequest } =
      await waitForRequest('@getFormCommands');

    expect(commandsRequest.url.pathname).toContain(
      '/api/configuration/services/commands'
    );
    expect(commandsRequest.url.searchParams.getAll('type[]')).toEqual([
      'Check'
    ]);
    expect(commandsRequest.url.searchParams.get('is_activated')).toBe('true');

    await userEvent.type(await findArgs(), '!3!80%');

    await addMacro();
    await userEvent.type(await findMacroInput(0, 'name'), ' port ');
    await userEvent.type(await findMacroInput(0, 'value'), '161');

    await save();

    const { request } = await waitForRequest('@createService');

    // Named as the server stores them: trimmed, upper-cased.
    expect((request.body as { check_options: unknown }).check_options).toEqual({
      args: ['3', '80%'],
      command_id: 9,
      macros: [
        {
          id: null,
          is_password: false,
          name: 'PORT',
          parent: null,
          value: '161'
        }
      ]
    });
  });

  it('refuses arguments partly filled or holding an escape token', async () => {
    await openService0();

    const args = await findArgs();

    await waitFor(() => expect(args).toHaveValue('!80!90'));

    await replaceValue(args, '!80!!90');
    leave(args);
    await expectError(args, labelFillAllArgumentsOrNone);

    await replaceValue(args, '!80#BR#!90');
    await expectError(args, labelArgumentsForbiddenTokens);
    expect(getSaveButton()).toBeDisabled();
  });

  it('refuses a reserved, a duplicate or a valueless macro', async () => {
    await openService0();

    await findMacroInput(2, 'name');

    await addMacro();
    const name = await findMacroInput(3, 'name');

    await userEvent.type(name, 'criticality_level');
    leave(name);
    await expectError(name, labelReservedMacroName);

    // The server upper-cases names: `warning` is `WARNING`.
    await replaceValue(name, 'warning');
    await expectError(name, labelAlreadyExists);

    await replaceValue(name, 'critical');
    const value = await findMacroInput(3, 'value');

    leave(value);
    await expectError(value, labelRequired);
    expect(getSaveButton()).toBeDisabled();
  });

  it('marks an inherited macro with a bar until it is edited', async () => {
    await openService0();

    const expectRowBar = (index: number, barColor: string): Promise<void> =>
      waitFor(async () =>
        expect(
          getComputedStyle(
            await screen.findByTestId(`${macrosTestId}-${index}-inherited-bar`)
          ).backgroundColor
        ).toBe(barColor)
      );

    expect(await findMacroInput(2, 'name')).toHaveValue('TIMEOUT');
    await expectRowBar(0, 'rgba(0, 0, 0, 0)');
    await expectRowBar(2, 'rgb(252, 196, 129)');

    await replaceValue(await findMacroInput(2, 'value'), '20');
    await expectRowBar(2, 'rgba(0, 0, 0, 0)');
  });

  it('updates a service, keeping its stored password and leaving its inherited macros out', async () => {
    await openService0();

    await replaceValue(await findMacroInput(0, 'value'), '85');

    await save();

    const { request } = await waitForRequest('@updateService0');

    expect((request.body as { check_options: unknown }).check_options).toEqual({
      args: ['80', '90'],
      command_id: 9,
      macros: [
        {
          id: 1,
          is_password: false,
          name: 'WARNING',
          parent: null,
          value: '85'
        },
        // Null keeps the stored password.
        {
          id: 2,
          is_password: true,
          name: 'API_TOKEN',
          parent: null,
          value: null
        }
      ]
    });
  });

  it('hides the arguments once the command is removed', async () => {
    await openService0();

    await waitFor(() =>
      expect(screen.getByTestId(commandTestId)).toHaveValue(
        'check-centreon-cpu'
      )
    );
    await findArgs();

    await clearCommand();
    // Service 0 also has a template, whose command keeps the arguments.
    expect(queryArgs()).not.toBeNull();
    await clearSelector('service-form-template');

    await waitFor(() => expect(queryArgs()).toBeNull());
    // Nothing else gives the service a command.
    await expectError(screen.getByTestId(commandTestId), labelRequired);
  });

  it('shows the check options read-only to a user who may only look at services', async () => {
    await openService0({ hasWriteAccess: false });

    await waitFor(() =>
      expect(screen.getByTestId(commandTestId)).toHaveValue(
        'check-centreon-cpu'
      )
    );
    expect(screen.getByTestId(commandTestId)).toBeDisabled();
    expect(await findArgs()).toBeDisabled();
    expect(await findMacroInput(0, 'value')).toBeDisabled();
  });
};
