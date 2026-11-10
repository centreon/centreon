import { unsavedChangesTranslatedLabels } from '@centreon/ui';

import { rs } from '@rstest/core';
import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelAddNewEntry,
  labelClose
} from '../../ConfigurationBase/translatedLabels';
import { labelCreateServicesLinkedToTemplates } from '../../Hosts/translatedLabels';
import initialize from './initialize';

const prefix = 'host-template-form';
const templatesTestId = `${prefix}-templates`;
const macrosTestId = `${prefix}-check-options-macros`;

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

const getTemplatesList = (): Promise<HTMLElement> =>
  screen.findByTestId(templatesTestId);

const addParent = async (index: number, name: string): Promise<void> => {
  await userEvent.click(
    within(await getTemplatesList()).getByRole('button', {
      name: labelAddNewEntry
    })
  );
  await userEvent.click(
    await screen.findByTestId(`${templatesTestId}-${index}`)
  );
  await waitFor(() => expect(getPopper()).not.toBeNull());
  // Scoped to the popper: the listing behind the panel shows the same names.
  await userEvent.click(await within(getPopper()).findByText(name));
  await waitFor(() =>
    expect(screen.getByTestId(`${templatesTestId}-${index}`)).toHaveValue(name)
  );
};

const deleteFirstParent = async (): Promise<void> => {
  await userEvent.click(
    within(await getTemplatesList()).getAllByTestId('delete-row')[0]
  );
};

const getDragHandles = (): Array<HTMLElement> =>
  within(screen.getByTestId(templatesTestId)).getAllByTestId('drag-handle');

// The dnd-kit keyboard sensor: pick the first row up, move it down one row,
// drop it. Each step waits for dnd-kit to have handled the previous key.
const moveFirstParentDown = async (): Promise<void> => {
  getDragHandles()[0].focus();
  await userEvent.keyboard(' ');
  await waitFor(() =>
    expect(getDragHandles()[0]).toHaveAttribute('aria-pressed', 'true')
  );
  await userEvent.keyboard('{ArrowDown}');
  await screen.findByText('Row 1 moved to position 2');
  await userEvent.keyboard(' ');
  await screen.findByText('Row 1 dropped at position 2');
};

// Every macro row shown, by name.
const getMacros = (): Record<string, string> =>
  Object.fromEntries(
    Array.from(
      document.querySelectorAll<HTMLInputElement>(
        `input[data-testid^="${macrosTestId}-"][data-testid$="-name"]`
      )
    ).map((nameInput) => [
      nameInput.value,
      document.querySelector<HTMLInputElement>(
        `input[data-testid="${nameInput.dataset.testid?.replace(/-name$/, '-value')}"]`
      )?.value ?? ''
    ])
  );

const expectMacros = async (macros: Record<string, string>): Promise<void> => {
  await waitFor(() => expect(getMacros()).toEqual(macros));
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

  await userEvent.type(await getInput(`${prefix}-name`), 'linux');
  await userEvent.type(await getInput(`${prefix}-alias`), 'Linux');
};

const openTemplate = async (id: number): Promise<void> => {
  initialize({ search: `?mode=edit&id=${id}` });

  await waitForRequest(`@getHostTemplate${id}`);
};

const { labelDiscard } = unsavedChangesTranslatedLabels;

// Template 1 with an unsaved alias, the confirmation open on its first parent.
const editParentOfChangedTemplate = async (): Promise<HTMLElement> => {
  await openTemplate(1);

  const alias = await getInput(`${prefix}-alias`);

  await waitFor(() => expect(alias).toHaveValue('Generic active host'));
  await userEvent.type(alias, ' changed');

  await userEvent.click(screen.getAllByTestId(`${templatesTestId}-edit`)[0]);

  return screen.findByRole('dialog');
};

export default () => {
  describe('Parent templates: ', () => {
    it('opens a template on its parents, in order', async () => {
      await openTemplate(3);

      await waitFor(() =>
        expect(screen.getByTestId(`${templatesTestId}-0`)).toHaveValue(
          'generic-active-host'
        )
      );
      expect(screen.getByTestId(`${templatesTestId}-1`)).toHaveValue(
        'OS-Linux-SNMP'
      );
    });

    it('creates a template with its parents in order, offered without exclusion', async () => {
      await openCreationForm();

      expect(
        screen.queryByLabelText(labelCreateServicesLinkedToTemplates)
      ).not.toBeInTheDocument();

      await addParent(0, 'OS-Linux-SNMP');
      const { request: choicesRequest } = await waitForRequest(
        '@getFormParentTemplates'
      );

      expect(choicesRequest.url.pathname).toContain(
        '/api/configuration/host_templates/host_templates'
      );
      expect(choicesRequest.url.searchParams.has('exclude_template_id')).toBe(
        false
      );

      await addParent(1, 'generic-active-host');

      await save();

      const { request } = await waitForRequest('@createHostTemplate');
      const body = request.body as Record<string, unknown>;

      expect(body.template_ids).toEqual([4, 2]);
      expect(body).not.toHaveProperty('create_services_linked_to_templates');
    });

    it('never offers the edited template as its own parent', async () => {
      await openTemplate(1);

      await waitFor(() =>
        expect(screen.getByTestId(`${templatesTestId}-0`)).toHaveValue(
          'generic-active-host'
        )
      );
      await userEvent.click(
        within(await getTemplatesList()).getByRole('button', {
          name: labelAddNewEntry
        })
      );
      await userEvent.click(await screen.findByTestId(`${templatesTestId}-1`));

      const { request } = await waitForRequest(
        '@getFormParentTemplatesExcluding1'
      );

      expect(request.url.searchParams.get('exclude_template_id')).toBe('1');
      await waitFor(() => expect(getPopper()).not.toBeNull());
      expect(await within(getPopper()).findByText('linux-base')).toBeVisible();
      expect(
        within(getPopper()).queryByText('generic-active-host-custom')
      ).not.toBeInTheDocument();
      expect(getRequestCalls('getFormParentTemplates')).toHaveLength(0);
    });

    it('saves the parents in the order they were dragged to', async () => {
      await openCreationForm();

      await addParent(0, 'OS-Linux-SNMP');
      await addParent(1, 'generic-active-host');

      await moveFirstParentDown();

      await waitFor(() =>
        expect(screen.getByTestId(`${templatesTestId}-0`)).toHaveValue(
          'generic-active-host'
        )
      );

      await save();

      const { request } = await waitForRequest('@createHostTemplate');

      expect((request.body as Record<string, unknown>).template_ids).toEqual([
        2, 4
      ]);
    });

    it('shows what each parent lends, the first one prevailing, as parents are added, reordered and removed', async () => {
      await openCreationForm();

      await addParent(0, 'generic-active-host');
      const { request } = await waitForRequest('@getTemplateMacros2');

      expect(request.url.pathname).toContain(
        '/api/configuration/host_templates/host_templates/2/macros'
      );
      await expectMacros({ OS: 'linux', TIMEOUT: '10' });

      // Added second: template 2 still gives OS.
      await addParent(1, 'OS-Linux-SNMP');
      await waitForRequest('@getTemplateMacros4');
      await expectMacros({ OS: 'linux', TIMEOUT: '10', WARNING: '80' });

      // Moved first: template 4 gives OS now.
      await moveFirstParentDown();
      await waitFor(() =>
        expect(screen.getByTestId(`${templatesTestId}-0`)).toHaveValue(
          'OS-Linux-SNMP'
        )
      );
      await expectMacros({ OS: 'windows', TIMEOUT: '10', WARNING: '80' });

      // Removed: only template 2 lends.
      await deleteFirstParent();
      await expectMacros({ OS: 'linux', TIMEOUT: '10' });
    });

    it('sends no inherited macro unless the template changes it', async () => {
      await openCreationForm();

      await addParent(0, 'generic-active-host');
      await waitForRequest('@getTemplateMacros2');
      await expectMacros({ OS: 'linux', TIMEOUT: '10' });

      await save();

      const { request } = await waitForRequest('@createHostTemplate');

      expect(
        (request.body as { check_options: { macros: unknown } }).check_options
          .macros
      ).toEqual([]);
    });

    it('opens a parent in this panel', async () => {
      const open = rs.spyOn(window, 'open').mockImplementation(() => null);

      await openTemplate(1);

      await waitFor(() =>
        expect(screen.getByTestId(`${templatesTestId}-0`)).toHaveValue(
          'generic-active-host'
        )
      );
      await userEvent.click(
        screen.getAllByTestId(`${templatesTestId}-edit`)[0]
      );

      await waitForRequest('@getHostTemplate2');
      await waitFor(() =>
        expect(screen.getByTestId(panelDataTestIds.header)).toHaveTextContent(
          /^generic-active-host$/
        )
      );
      expect(window.location.search).toBe('?id=2&mode=edit');
      expect(open).not.toHaveBeenCalled();

      open.mockRestore();
    });

    it('asks to confirm unsaved changes before opening a parent', async () => {
      await openTemplate(1);

      const alias = await getInput(`${prefix}-alias`);

      await waitFor(() => expect(alias).toHaveValue('Generic active host'));
      await userEvent.type(alias, ' changed');

      await userEvent.click(
        screen.getAllByTestId(`${templatesTestId}-edit`)[0]
      );

      expect(await screen.findByRole('dialog')).toBeVisible();
      expect(getRequestCalls('getHostTemplate2')).toHaveLength(0);
      expect(window.location.search).toBe('?mode=edit&id=1');
    });

    it('opens the parent once the unsaved changes are discarded', async () => {
      const dialog = await editParentOfChangedTemplate();

      await userEvent.click(
        within(dialog).getByRole('button', { name: labelDiscard })
      );

      await waitForRequest('@getHostTemplate2');
      await waitFor(() =>
        expect(screen.getByTestId(panelDataTestIds.header)).toHaveTextContent(
          /^generic-active-host$/
        )
      );
      expect(window.location.search).toBe('?id=2&mode=edit');
    });

    it('stays on the template when the confirmation is dismissed, and only closes on a later discard', async () => {
      await editParentOfChangedTemplate();

      await userEvent.keyboard('{Escape}');

      await waitFor(() =>
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
      );
      expect(await getInput(`${prefix}-alias`)).toHaveValue(
        'Generic active host changed'
      );
      expect(window.location.search).toBe('?mode=edit&id=1');

      await userEvent.click(screen.getByRole('button', { name: labelClose }));
      await userEvent.click(
        within(await screen.findByRole('dialog')).getByRole('button', {
          name: labelDiscard
        })
      );

      await waitFor(() =>
        expect(
          screen.queryByTestId(panelDataTestIds.header)
        ).not.toBeInTheDocument()
      );
      expect(getRequestCalls('getHostTemplate2')).toHaveLength(0);
      expect(window.location.search).toBe('');
    });

    it('opens a template with its parents read-only for a user who may only look at templates', async () => {
      initialize({ hasWriteAccess: false, search: '?mode=edit&id=1' });

      await waitForRequest('@getHostTemplate1');

      const firstParent = await screen.findByTestId(`${templatesTestId}-0`);

      await waitFor(() =>
        expect(firstParent).toHaveValue('generic-active-host')
      );
      expect(firstParent).toBeDisabled();

      const list = await getTemplatesList();

      expect(
        within(list).getByRole('button', { name: labelAddNewEntry })
      ).toBeDisabled();
      expect(within(list).getAllByTestId('delete-row')[0]).toBeDisabled();
      expect(
        screen.getAllByTestId(`${templatesTestId}-edit`)[0]
      ).toBeDisabled();
      expect(within(list).queryAllByTestId('drag-handle')).toHaveLength(0);
    });

    it('opens a locked template with its parents read-only', async () => {
      await openTemplate(3);

      await waitFor(() =>
        expect(screen.getByTestId(`${templatesTestId}-0`)).toBeDisabled()
      );
      expect(
        within(await getTemplatesList()).getByRole('button', {
          name: labelAddNewEntry
        })
      ).toBeDisabled();
    });
  });
};
