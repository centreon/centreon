import { interceptAPIRequest, Method, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelCommandInheritance,
  labelTemplateInheritance
} from '../../ConfigurationBase/translatedLabels';
import { getHostTemplateMacrosEndpoint } from '../api/endpoints';
import { labelAddNewEntry, labelAlreadyExists } from '../translatedLabels';
import initialize from './initialize';
import { untouchedCheckOptionsPayload } from './utils';

const macrosTestId = 'host-form-check-options-macros';

const macroInputSelector = (index: number, key: 'name' | 'value'): string =>
  `input[data-testid="${macrosTestId}-${index}-${key}"]`;

const queryMacroInput = (
  index: number,
  key: 'name' | 'value'
): HTMLInputElement | null =>
  document.querySelector<HTMLInputElement>(macroInputSelector(index, key));

// Rendered once the host or the templates are loaded: waited for.
const findMacroInput = async (
  index: number,
  key: 'name' | 'value'
): Promise<HTMLInputElement> => {
  await waitFor(() => expect(queryMacroInput(index, key)).not.toBeNull());

  return queryMacroInput(index, key) as HTMLInputElement;
};

const findMacroBar = (index: number): Promise<HTMLElement> =>
  screen.findByTestId(`${macrosTestId}-${index}-inherited-bar`);

const expectRowBar = async (index: number, barColor: string): Promise<void> => {
  await waitFor(async () =>
    expect(getComputedStyle(await findMacroBar(index)).backgroundColor).toBe(
      barColor
    )
  );
};

const expectMacroValue = async (
  index: number,
  key: 'name' | 'value',
  value: string
): Promise<void> => {
  await waitFor(() => expect(queryMacroInput(index, key)).toHaveValue(value));
};

// Waited for until that row is rendered, not only the first one.
const getPassword = async (index: number): Promise<HTMLElement> => {
  await waitFor(() =>
    expect(
      screen.getAllByTestId(`${macrosTestId}-password`).length
    ).toBeGreaterThan(index)
  );

  return screen.getAllByTestId(`${macrosTestId}-password`)[index];
};

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

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper: the listing behind the panel shows the same names.
const pickOption = async (
  field: HTMLElement,
  option: string
): Promise<void> => {
  await userEvent.click(field);
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

// A response the page shows nothing for (a 404, a failure past its toast)
// has no landing of its own to wait for. A round trip started after it does:
// opening the check command's options asks the server for them, and the
// options shown prove the page has processed the responses sent before.
// Closed with Escape, so the form is left as it was.
const letEarlierResponsesLand = async (): Promise<void> => {
  await userEvent.click(
    await screen.findByTestId('host-form-check-options-command')
  );
  await waitForRequest('@getFormCommands');
  await within(getPopper()).findByText('notify-by-email');
  await userEvent.keyboard('{Escape}');
  await waitFor(() => expect(getPopper()).toBeNull());
};

const save = async (): Promise<void> => {
  const button = document.querySelector(
    `button[data-testid="${panelDataTestIds.save}"]`
  ) as HTMLButtonElement;

  await waitFor(() => expect(button).toBeEnabled());
  await userEvent.click(button);
};

const openHost0 = async (): Promise<void> => {
  initialize({});

  await waitForRequest('@getAllHosts');

  await userEvent.click(await screen.findByText('host 0'));

  await waitForRequest('@getHost');
};

const openCreationForm = async (): Promise<void> => {
  initialize({});

  await waitForRequest('@getAllHosts');
  // The listing rendered: the Add button opens the panel from then on.
  await screen.findByText('host 0');

  await userEvent.click(await screen.findByTestId('add-resource'));
};

const fillRequiredFields = async (): Promise<void> => {
  await userEvent.type(
    (await screen.findAllByTestId('host-form-name'))[1],
    'srv-apache-02'
  );
  await userEvent.type(
    (await screen.findAllByTestId('host-form-address'))[1],
    '10.0.0.42'
  );

  await pickOption(screen.getByTestId('host-form-poller'), 'Poller EU');
};

export default () => {
  it('creates a host with its custom macros, a password one masked', async () => {
    await openCreationForm();

    await fillRequiredFields();

    await addMacro();
    await userEvent.type(await findMacroInput(0, 'name'), ' snmpport ');
    await userEvent.type(await findMacroInput(0, 'value'), '161');
    expect(await findMacroInput(0, 'value')).toHaveAttribute('type', 'text');

    await addMacro();
    await userEvent.type(await findMacroInput(1, 'name'), 'api_token');
    await userEvent.type(await findMacroInput(1, 'value'), 's3cret');
    await userEvent.click(await getPassword(1));
    await waitFor(() =>
      expect(queryMacroInput(1, 'value')).toHaveAttribute('type', 'password')
    );
    expect(queryMacroInput(1, 'value')).toHaveValue('s3cret');

    await save();

    const { request } = await waitForRequest('@createHost');

    // Named as the server stores them: trimmed, upper-cased.
    expect((request.body as { check_options: unknown }).check_options).toEqual({
      ...untouchedCheckOptionsPayload,
      macros: [
        {
          id: null,
          is_password: false,
          name: 'SNMPPORT',
          parent: null,
          value: '161'
        },
        {
          id: null,
          is_password: true,
          name: 'API_TOKEN',
          parent: null,
          value: 's3cret'
        }
      ]
    });
  });

  it('opens an existing host on its custom macros', async () => {
    await openHost0();

    await expectMacroValue(0, 'name', 'SNMPPORT');
    await expectMacroValue(0, 'value', '161');
    await expectMacroValue(1, 'name', 'API_TOKEN');
    // Masked and locked: the API never sends a password back.
    await waitFor(() => {
      const input = queryMacroInput(1, 'value');

      expect(input).toHaveAttribute('type', 'password');
      expect(input).toHaveValue('********');
      expect(input).toBeDisabled();
    });
    await expectMacroValue(2, 'name', 'OS');
    await expectMacroValue(3, 'name', 'WARNING');
  });

  it('outlines inherited macros, until they are changed', async () => {
    await openHost0();

    const macros = await screen.findByTestId(macrosTestId);

    const templateInheritance = await within(macros).findByText(
      labelTemplateInheritance
    );
    templateInheritance.scrollIntoView();
    await waitFor(() => expect(templateInheritance).toBeVisible());
    const commandInheritance = await within(macros).findByText(
      labelCommandInheritance
    );
    commandInheritance.scrollIntoView();
    await waitFor(() => expect(commandInheritance).toBeVisible());

    await expectRowBar(0, 'rgba(0, 0, 0, 0)');
    await expectRowBar(2, 'rgb(252, 196, 129)');
    await expectRowBar(3, 'rgb(159, 199, 78)');

    // The design's bar: 4px wide, 36px high, 2px radius.
    const bar = getComputedStyle(await findMacroBar(2));
    expect([bar.width, bar.height, bar.borderTopLeftRadius]).toStrictEqual([
      '4px',
      '36px',
      '2px'
    ]);

    // Overridden: the host's own from now on.
    await replaceValue(await findMacroInput(2, 'value'), 'debian');
    await expectRowBar(2, 'rgba(0, 0, 0, 0)');
    // Back to the inherited value: inherited again.
    await replaceValue(await findMacroInput(2, 'value'), 'linux');
    await expectRowBar(2, 'rgb(252, 196, 129)');
  });

  it('overrides an inherited macro and replaces a stored password', async () => {
    await openHost0();

    await userEvent.click(
      await screen.findByTestId(`${macrosTestId}-1-value-clear`)
    );
    await userEvent.type(await findMacroInput(1, 'value'), 'n3w');
    await replaceValue(await findMacroInput(2, 'value'), 'debian');

    await save();

    const { request } = await waitForRequest('@patchHost');

    // Id and parent tell the server which inherited macro this overrides.
    expect(
      (
        request.body as { check_options: { macros: Array<unknown> } }
      ).check_options.macros.slice(1)
    ).toEqual([
      {
        id: 2,
        is_password: true,
        name: 'API_TOKEN',
        parent: null,
        value: 'n3w'
      },
      {
        id: 11,
        is_password: false,
        name: 'OS',
        parent: 'template',
        value: 'debian'
      }
    ]);
  });

  it('keeps a stored password one, replaced only once cleared', async () => {
    await openHost0();

    // As legacy: a stored password stays one; other macros may change.
    await waitFor(async () => expect(await getPassword(1)).toBeDisabled());
    expect(await getPassword(0)).toBeEnabled();

    await userEvent.click(
      await screen.findByTestId(`${macrosTestId}-1-value-clear`)
    );
    await waitFor(() => {
      const input = queryMacroInput(1, 'value');

      expect(input).toBeEnabled();
      expect(input).toHaveValue('');
      expect(input).toHaveFocus();
    });

    // Back to keeping the stored one.
    await userEvent.click(
      await screen.findByTestId(`${macrosTestId}-1-value-revert`)
    );
    await waitFor(() => {
      const input = queryMacroInput(1, 'value');

      expect(input).toBeDisabled();
      expect(input).toHaveValue('********');
    });

    // Cleared and left empty: an empty password.
    await userEvent.click(
      await screen.findByTestId(`${macrosTestId}-1-value-clear`)
    );
    await save();

    const { request } = await waitForRequest('@patchHost');

    expect(
      (request.body as { check_options: { macros: Array<unknown> } })
        .check_options.macros[1]
    ).toEqual({
      id: 2,
      is_password: true,
      name: 'API_TOKEN',
      parent: null,
      value: ''
    });
  });

  it('lets a macro added on edition turn into a password and back', async () => {
    await openHost0();

    await findMacroInput(3, 'name');

    await addMacro();
    await userEvent.click(await getPassword(4));
    await waitFor(() =>
      expect(queryMacroInput(4, 'value')).toHaveAttribute('type', 'password')
    );
    await userEvent.click(await getPassword(4));
    await waitFor(() =>
      expect(queryMacroInput(4, 'value')).toHaveAttribute('type', 'text')
    );
  });

  it('deletes a custom macro by leaving it out', async () => {
    await openHost0();

    await findMacroInput(0, 'name');

    await userEvent.click(
      within(await screen.findByTestId(macrosTestId)).getAllByTestId(
        'delete-row'
      )[0]
    );

    await save();

    const { request } = await waitForRequest('@patchHost');

    // The stored password keeps its own id and null value.
    expect(
      (request.body as { check_options: { macros: Array<unknown> } })
        .check_options.macros
    ).toEqual([
      {
        id: 2,
        is_password: true,
        name: 'API_TOKEN',
        parent: null,
        value: null
      }
    ]);
  });

  describe('inherited from the templates picked', () => {
    const templateBar = 'rgb(252, 196, 129)';
    const commandBar = 'rgb(159, 199, 78)';

    const openCreation = openCreationForm;

    const addTemplate = async (index: number, name: string): Promise<void> => {
      await userEvent.click(
        within(await screen.findByTestId('host-form-templates')).getByRole(
          'button',
          { name: labelAddNewEntry }
        )
      );
      await pickOption(
        await screen.findByTestId(`host-form-templates-${index}`),
        name
      );
    };

    const deleteFirstTemplate = async (): Promise<void> => {
      await userEvent.click(
        within(await screen.findByTestId('host-form-templates')).getAllByTestId(
          'delete-row'
        )[0]
      );
    };

    it('shows what each template lends, the first one prevailing', async () => {
      await openCreation();

      await addTemplate(0, 'generic-active-host');
      const { request } = await waitForRequest('@getTemplateMacros5');

      expect(request.url.pathname).toContain(
        '/api/configuration/hosts/host_templates/5'
      );

      await expectMacroValue(0, 'name', 'OS');
      await expectMacroValue(0, 'value', 'linux');
      await expectRowBar(0, templateBar);
      await expectMacroValue(1, 'name', 'TIMEOUT');
      await expectMacroValue(2, 'name', 'WARNING');
      await expectRowBar(2, commandBar);

      await addTemplate(1, 'generic-passive-host');
      await waitForRequest('@getTemplateMacros6');

      // Template 5 still gives OS; template 6's WARNING beats the command's.
      await expectMacroValue(0, 'value', 'linux');
      await expectMacroValue(1, 'name', 'TIMEOUT');
      await expectMacroValue(2, 'name', 'WARNING');
      await expectMacroValue(2, 'value', '80');
      await expectRowBar(2, templateBar);

      await deleteFirstTemplate();

      // Only template 6 left.
      await expectMacroValue(0, 'name', 'WARNING');
      await expectMacroValue(1, 'name', 'OS');
      await expectMacroValue(1, 'value', 'windows');
      await waitFor(() => expect(queryMacroInput(2, 'name')).toBeNull());
    });

    it("leaves the templates' command macros out once a check command is picked", async () => {
      await openCreation();

      await pickOption(
        await screen.findByTestId('host-form-check-options-command'),
        'check-host-alive'
      );

      await addTemplate(0, 'generic-active-host');
      await waitForRequest('@getTemplateMacros5');

      await expectMacroValue(1, 'name', 'TIMEOUT');
      await waitFor(() => expect(queryMacroInput(2, 'name')).toBeNull());
    });

    it('lends nothing, silently, while the endpoint answers 404', async () => {
      await openCreation();

      await addTemplate(0, 'linux-server-standard');
      await waitForRequest('@getTemplateMacros7');
      await letEarlierResponsesLand();

      await waitFor(() => expect(queryMacroInput(0, 'name')).toBeNull());
      expect(
        screen.queryByText('Host template not found')
      ).not.toBeInTheDocument();
    });

    it('sends only the macros the host changes or owns', async () => {
      await openCreation();

      await fillRequiredFields();

      await addTemplate(0, 'generic-active-host');
      await waitForRequest('@getTemplateMacros5');

      await replaceValue(await findMacroInput(1, 'value'), '20');
      await expectRowBar(1, 'rgba(0, 0, 0, 0)');

      await save();

      const { request } = await waitForRequest('@createHost');

      // Id and parent make TIMEOUT an override of template 5's.
      expect(
        (request.body as { check_options: { macros: Array<unknown> } })
          .check_options.macros
      ).toEqual([
        {
          id: 31,
          is_password: false,
          name: 'TIMEOUT',
          parent: 'template',
          value: '20'
        }
      ]);
    });

    it('keeps the rows as they are when a template fails to load', async () => {
      initialize({});

      // Added after the ones of initialize, so it takes precedence.
      interceptAPIRequest({
        alias: 'getTemplateMacros5',
        method: Method.GET,
        path: `**${getHostTemplateMacrosEndpoint({ id: 5 })}`,
        response: { code: 500, message: 'Internal error' },
        statusCode: 500
      });

      await waitForRequest('@getAllHosts');

      await userEvent.click(await screen.findByText('host 0'));

      await waitForRequest('@getHost');

      await findMacroInput(2, 'name');

      await deleteFirstTemplate();
      await waitForRequest('@getTemplateMacros5');

      expect(await screen.findByText('Internal error')).toBeVisible();
      // The toast comes before the page is done with the failure.
      await letEarlierResponsesLand();
      await expectMacroValue(2, 'name', 'OS');
      await expectMacroValue(2, 'value', 'linux');
      await expectMacroValue(3, 'name', 'WARNING');
    });

    it('keeps the rows as they are, silently, when a template is not found', async () => {
      await openHost0();

      await findMacroInput(2, 'name');

      // Host 0 has templates 6 and 5; template 7 answers 404.
      await addTemplate(2, 'linux-server-standard');
      await waitForRequest('@getTemplateMacros7');
      await letEarlierResponsesLand();

      // The detail's OS, not template 6's: nothing was resolved again.
      await expectMacroValue(2, 'name', 'OS');
      await expectMacroValue(2, 'value', 'linux');
      await expectMacroValue(3, 'name', 'WARNING');
      await expectRowBar(2, templateBar);
      expect(
        screen.queryByText('Host template not found')
      ).not.toBeInTheDocument();
    });

    it('follows the templates changed on an existing host', async () => {
      await openHost0();

      // As loaded: nothing fetched, the detail's inherited macros shown.
      await expectMacroValue(2, 'name', 'OS');
      await expectMacroValue(3, 'name', 'WARNING');

      // Host 0's templates are 6 then 5: keep 5 alone.
      await deleteFirstTemplate();
      await waitForRequest('@getTemplateMacros5');

      // Host 0 keeps its own check command's WARNING, not template 5's
      // command macros; template 5's OS replaces template 6's.
      await expectMacroValue(0, 'name', 'SNMPPORT');
      await expectMacroValue(1, 'name', 'API_TOKEN');
      await expectMacroValue(2, 'name', 'WARNING');
      await expectMacroValue(3, 'name', 'OS');
      await expectMacroValue(3, 'value', 'linux');
      await expectMacroValue(4, 'name', 'TIMEOUT');
      await waitFor(() => expect(queryMacroInput(5, 'name')).toBeNull());
    });
  });

  it('refuses two custom macros of the same name', async () => {
    await openCreationForm();

    await fillRequiredFields();

    await addMacro();
    await userEvent.type(await findMacroInput(0, 'name'), 'port');
    await addMacro();
    // The same name once the server upper-cases it.
    await userEvent.type(await findMacroInput(1, 'name'), 'PORT ');
    await userEvent.tab();

    expect(
      await within(screen.getByTestId(macrosTestId)).findByText(
        labelAlreadyExists
      )
    ).toBeVisible();
    await waitFor(() =>
      expect(
        document.querySelector(`button[data-testid="${panelDataTestIds.save}"]`)
      ).toBeDisabled()
    );
  });
};
