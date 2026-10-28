import { rs } from '@rstest/core';
import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelAddNewEntry,
  labelCreateServicesLinkedToTemplates,
  labelInvalidGeographicCoordinates
} from '../translatedLabels';
import initialize from './initialize';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHosts');
  await screen.findByText('host 0');
};

const openHost = async (name: string, alias: string): Promise<void> => {
  await userEvent.click(await screen.findByText(name));

  await waitForRequest(alias);
};

const openCreation = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId('add-resource'));
};

// `@centreon/ui` text fields put the test id on the wrapper and the input:
// the second one is the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

// The last one opened: a closing popper may still be in the page.
const getPopper = async (): Promise<HTMLElement> => {
  await waitFor(() =>
    expect(document.querySelector('.MuiAutocomplete-popper')).not.toBeNull()
  );

  return [
    ...document.querySelectorAll<HTMLElement>('.MuiAutocomplete-popper')
  ].at(-1) as HTMLElement;
};

const pickOption = async (label: string): Promise<void> => {
  await userEvent.click(await within(await getPopper()).findByText(label));
};

const pickPoller = async (): Promise<void> => {
  await userEvent.click(screen.getByTestId('host-form-poller'));
  await pickOption('Poller EU');
};

const fillNameAndAddress = async (): Promise<void> => {
  await userEvent.type(await getInput('host-form-name'), 'srv-apache-02');
  await userEvent.type(await getInput('host-form-address'), '10.0.0.42');
};

const addTemplateRow = async (): Promise<void> => {
  await userEvent.click(
    within(screen.getByTestId('host-form-templates')).getByRole('button', {
      name: labelAddNewEntry
    })
  );
};

const clickClearIndicator = async (testId: string): Promise<void> => {
  // The clear indicator only shows on hover, which `{ force: true }` skipped
  // in Cypress; userEvent clicks it whatever its visibility.
  await userEvent.click(
    screen
      .getByTestId(testId)
      .closest('.MuiAutocomplete-root')
      ?.querySelector('.MuiAutocomplete-clearIndicator') as HTMLElement
  );
};

const save = async (): Promise<void> => {
  await userEvent.click(
    document.querySelector(
      `button[data-testid="${panelDataTestIds.save}"]`
    ) as HTMLButtonElement
  );
};

const getTemplateDragHandles = (): Array<HTMLElement> =>
  within(screen.getByTestId('host-form-templates')).getAllByTestId(
    'drag-handle'
  );

export default () => {
  it('opens an existing host on its extended infos and severity', async () => {
    await initializeListing();

    await openHost('host 0', '@getHost');

    const note = await getInput('host-form-extended-infos-note');

    await waitFor(() => expect(note).toHaveValue('Front web server'));
    // Left out of the response, so still empty.
    expect(await getInput('host-form-extended-infos-noteUrl')).toHaveValue('');
    expect(await getInput('host-form-extended-infos-actionUrl')).toHaveValue(
      'https://example.com/actions/host-0'
    );
    expect(
      await getInput('host-form-extended-infos-geoCoordinates')
    ).toHaveValue('48.8566,2.3522');
    expect(await getInput('host-form-extended-infos-altIcon')).toHaveValue('');
    expect(await getInput('host-form-extended-infos-comment')).toHaveValue(
      'Racked in room B'
    );
    await waitFor(() =>
      expect(screen.getByTestId('host-form-extended-infos-icon')).toHaveValue(
        'server.png'
      )
    );
    await waitFor(() =>
      expect(
        screen
          .getByTestId('host-form-extended-infos-icon-preview')
          .querySelector('img')
      ).toHaveAttribute('alt', 'server.png')
    );
    await waitFor(() =>
      expect(screen.getByTestId('host-form-severity')).toHaveValue('Minor')
    );
  });

  it('opens a host with no extended infos on empty fields', async () => {
    await initializeListing();

    await openHost('host 1', '@getHost1');

    const name = await getInput('host-form-name');

    await waitFor(() => expect(name).toHaveValue('host 1'));
    expect(await getInput('host-form-extended-infos-note')).toHaveValue('');
    expect(screen.getByTestId('host-form-extended-infos-icon')).toHaveValue('');
    expect(
      screen
        .getByTestId('host-form-extended-infos-icon-preview')
        .querySelector('img')
    ).not.toBeInTheDocument();
    expect(screen.getByTestId('host-form-severity')).toHaveValue('');
  });

  it('clears the extended infos and severity of a host', async () => {
    await initializeListing();

    await openHost('host 0', '@getHost');

    const note = await getInput('host-form-extended-infos-note');

    await waitFor(() => expect(note).toHaveValue('Front web server'));
    await userEvent.clear(note);
    const actionUrl = await getInput('host-form-extended-infos-actionUrl');

    await userEvent.clear(actionUrl);
    await userEvent.type(actionUrl, '   ');
    await waitFor(() =>
      expect(screen.getByTestId('host-form-extended-infos-icon')).toHaveValue(
        'server.png'
      )
    );
    await clickClearIndicator('host-form-extended-infos-icon');
    await waitFor(() =>
      expect(
        screen
          .getByTestId('host-form-extended-infos-icon-preview')
          .querySelector('img')
      ).not.toBeInTheDocument()
    );
    await waitFor(() =>
      expect(screen.getByTestId('host-form-severity')).toHaveValue('Minor')
    );
    await clickClearIndicator('host-form-severity');

    await save();

    const { request } = await waitForRequest('@patchHost');
    const body = request.body as Record<string, unknown>;

    expect(body.extended_informations).toMatchObject({
      action_url: null,
      icon_id: null,
      note: null
    });
    expect(body.severity_id).toBe(null);
  });

  it('lets a user who may only look at hosts change no extended info', async () => {
    await initializeListing({ hasWriteAccess: false });

    await openHost('host 0', '@getHost');

    const note = await getInput('host-form-extended-infos-note');

    await waitFor(() => expect(note).toBeDisabled());
    expect(await getInput('host-form-extended-infos-comment')).toBeDisabled();
    expect(screen.getByTestId('host-form-extended-infos-icon')).toBeDisabled();
    expect(screen.getByTestId('host-form-severity')).toBeDisabled();
  });

  it('creates a host with its extended infos and severity', async () => {
    await initializeListing();

    await openCreation();

    await fillNameAndAddress();

    await pickPoller();

    await userEvent.type(
      await getInput('host-form-extended-infos-note'),
      '  Front web server  '
    );
    await userEvent.type(
      await getInput('host-form-extended-infos-noteUrl'),
      'https://example.com/notes'
    );
    await userEvent.type(
      await getInput('host-form-extended-infos-actionUrl'),
      'https://example.com/actions'
    );
    await userEvent.type(
      await getInput('host-form-extended-infos-geoCoordinates'),
      '-33.8688,151.2093'
    );
    await userEvent.type(
      await getInput('host-form-extended-infos-altIcon'),
      'Web server'
    );
    await userEvent.type(
      await getInput('host-form-extended-infos-comment'),
      'Racked in room B'
    );

    await userEvent.click(screen.getByTestId('host-form-extended-infos-icon'));
    // The host-scoped selector, granted by host write access.
    const { request: mediasRequest } = await waitForRequest('@getFormMedias');

    expect(mediasRequest.url.pathname).toContain(
      '/api/configuration/hosts/medias'
    );
    await pickOption('router.png');
    await waitFor(() =>
      expect(
        screen
          .getByTestId('host-form-extended-infos-icon-preview')
          .querySelector('img')
      ).toHaveAttribute('alt', 'router.png')
    );

    await userEvent.click(screen.getByTestId('host-form-severity'));
    const { request: severitiesRequest } = await waitForRequest(
      '@getFormHostSeverities'
    );

    expect(severitiesRequest.url.pathname).toContain(
      '/api/configuration/hosts/host_severities'
    );
    await pickOption('Critical');

    await save();

    const { request } = await waitForRequest('@createHost');
    const body = request.body as Record<string, unknown>;

    expect(body.extended_informations).toEqual({
      action_url: 'https://example.com/actions',
      alt_icon: 'Web server',
      comment: 'Racked in room B',
      geo_coordinates: '-33.8688,151.2093',
      icon_id: 13,
      note: 'Front web server',
      note_url: 'https://example.com/notes'
    });
    // A field of the host, not of its extended informations.
    expect(body.severity_id).toBe(1);
  });

  it('refuses geographic coordinates out of range', async () => {
    await initializeListing();

    await openCreation();

    const geoCoordinates = await getInput(
      'host-form-extended-infos-geoCoordinates'
    );

    await userEvent.type(geoCoordinates, '91,2');
    await userEvent.tab();

    expect(
      await screen.findByText(labelInvalidGeographicCoordinates)
    ).toBeVisible();
  });

  it('offers no alt icon nor comments on a cloud platform', async () => {
    await initializeListing({ isCloudPlatform: true });

    await openCreation();

    for (const field of ['note', 'noteUrl', 'actionUrl', 'geoCoordinates']) {
      expect(
        await getInput(`host-form-extended-infos-${field}`)
      ).toBeInTheDocument();
    }
    expect(
      screen.getByTestId('host-form-extended-infos-icon')
    ).toBeInTheDocument();
    expect(screen.getByTestId('host-form-severity')).toBeInTheDocument();

    ['altIcon', 'comment'].forEach((field) => {
      expect(
        screen.queryByTestId(`host-form-extended-infos-${field}`)
      ).not.toBeInTheDocument();
    });
  });

  it('creates a host from its templates, in the order they were added', async () => {
    await initializeListing();

    await openCreation();

    await fillNameAndAddress();

    await pickPoller();

    await addTemplateRow();
    await userEvent.click(await screen.findByTestId('host-form-templates-0'));
    // The form's own selector, granted by host write access.
    const { request: templatesRequest } = await waitForRequest(
      '@getFormHostTemplates'
    );

    expect(templatesRequest.url.pathname).toContain(
      '/api/configuration/hosts/host_templates'
    );
    expect(templatesRequest.url.pathname).not.toContain('/api/latest');
    await pickOption('linux-server-standard');

    await addTemplateRow();
    await userEvent.click(await screen.findByTestId('host-form-templates-1'));
    // Not offered twice.
    await waitFor(async () =>
      expect(
        within(await getPopper())
          .getByText('linux-server-standard')
          .closest('li')
      ).toHaveAttribute('aria-disabled', 'true')
    );
    await pickOption('generic-active-host');

    // On by default when creating, as legacy does.
    const createServices = screen.getByLabelText(
      labelCreateServicesLinkedToTemplates
    );

    expect(createServices).toBeChecked();
    await userEvent.click(createServices);

    await save();

    const { request } = await waitForRequest('@createHost');
    const body = request.body as Record<string, unknown>;

    expect(body.template_ids).toEqual([7, 5]);
    expect(body.create_services_linked_to_templates).toBe(false);
  });

  it('searches the templates by the name typed', async () => {
    await initializeListing();

    await openCreation();

    await addTemplateRow();
    await userEvent.click(await screen.findByTestId('host-form-templates-0'));
    await pickOption('linux-server-standard');

    // A row with a value excludes it first: the typed text is not the first
    // search condition.
    const firstTemplate = screen.getByTestId('host-form-templates-0');

    await userEvent.clear(firstTemplate);
    await userEvent.type(firstTemplate, 'g');

    // The requests before the search come first: wait until one of them
    // searches the typed name.
    await waitFor(() =>
      expect(
        getRequestCalls('@getFormHostTemplates').map(({ request }) =>
          request.url.searchParams.get('name[lk]')
        )
      ).toContain('g')
    );
  });

  it('leaves out a template row left empty', async () => {
    await initializeListing();

    await openCreation();

    await fillNameAndAddress();

    await pickPoller();

    await addTemplateRow();
    await userEvent.click(await screen.findByTestId('host-form-templates-0'));
    await pickOption('linux-server-standard');
    await addTemplateRow();

    // Nothing picked yet: no template page to open.
    await waitFor(() =>
      expect(
        screen.getAllByTestId('host-form-templates-edit')[1]
      ).toBeDisabled()
    );

    await save();

    const { request } = await waitForRequest('@createHost');

    expect((request.body as Record<string, unknown>).template_ids).toEqual([7]);
  });

  it('lets a user who may only look at hosts change no template nor macro', async () => {
    await initializeListing({ hasWriteAccess: false });

    await openHost('host 0', '@getHost');

    await waitFor(() =>
      expect(screen.getByTestId('host-form-templates-0')).toBeDisabled()
    );
    expect(
      within(screen.getByTestId('host-form-templates')).getByRole('button', {
        name: labelAddNewEntry
      })
    ).toBeDisabled();
    expect(
      within(screen.getByTestId('host-form-check-options-macros')).getByRole(
        'button',
        { name: labelAddNewEntry }
      )
    ).toBeDisabled();
    await waitFor(() =>
      expect(
        document.querySelector(
          'input[data-testid="host-form-check-options-macros-0-value"]'
        )
      ).toBeDisabled()
    );
    expect(
      within(screen.getByTestId('host-form-templates')).getAllByTestId(
        'delete-row'
      )[0]
    ).toBeDisabled();
    expect(
      screen.getByLabelText(labelCreateServicesLinkedToTemplates)
    ).toBeDisabled();
  });

  it('opens an existing host on its templates, in order', async () => {
    await initializeListing();

    await openHost('host 0', '@getHost');

    await waitFor(() =>
      expect(screen.getByTestId('host-form-templates-0')).toHaveValue(
        'generic-passive-host'
      )
    );
    expect(screen.getByTestId('host-form-templates-1')).toHaveValue(
      'generic-active-host'
    );
    // Never read back: off, as legacy opens an existing host.
    expect(
      screen.getByLabelText(labelCreateServicesLinkedToTemplates)
    ).not.toBeChecked();
  });

  it('opens the configuration page of a template from its row', async () => {
    await initializeListing();

    await openHost('host 0', '@getHost');

    const open = rs.spyOn(window, 'open').mockImplementation(() => null);

    await waitFor(() =>
      expect(screen.getByTestId('host-form-templates-0')).toHaveValue(
        'generic-passive-host'
      )
    );
    await userEvent.click(screen.getAllByTestId('host-form-templates-edit')[0]);

    // Only the URL and the target, as Cypress' calledWithMatch checked: the
    // window features passed after them are left out.
    expect(
      open.mock.calls.map(([url, target]) => [url, target])
    ).toContainEqual([
      expect.stringMatching(/main\.php\?p=60103&o=c&host_id=6$/),
      '_blank'
    ]);

    open.mockRestore();
  });

  it('saves the templates in the order they were dragged to', async () => {
    await initializeListing();

    await openHost('host 0', '@getHost');

    await waitFor(() => expect(getTemplateDragHandles()).toHaveLength(2));

    // The dnd-kit keyboard sensor: pick the first row up, move it down one
    // row, drop it.
    const [firstHandle] = getTemplateDragHandles();

    // Each step waits for dnd-kit to have handled the previous key: the handle
    // pressed once the row is picked up (its announcement is replaced at once
    // by the move over its own position), then the announcements.
    firstHandle.focus();
    await userEvent.keyboard(' ');
    await waitFor(() =>
      expect(getTemplateDragHandles()[0]).toHaveAttribute(
        'aria-pressed',
        'true'
      )
    );
    await userEvent.keyboard('{ArrowDown}');
    await screen.findByText('Row 1 moved to position 2');
    await userEvent.keyboard(' ');
    await screen.findByText('Row 1 dropped at position 2');

    await waitFor(() =>
      expect(screen.getByTestId('host-form-templates-0')).toHaveValue(
        'generic-active-host'
      )
    );

    await save();

    const { request } = await waitForRequest('@patchHost');

    expect((request.body as Record<string, unknown>).template_ids).toEqual([
      5, 6
    ]);
  });

  it('saves the templates left once one is removed', async () => {
    await initializeListing();

    await openHost('host 0', '@getHost');

    await waitFor(() =>
      expect(screen.getByTestId('host-form-templates-0')).toHaveValue(
        'generic-passive-host'
      )
    );
    await userEvent.click(
      within(screen.getByTestId('host-form-templates')).getAllByTestId(
        'delete-row'
      )[0]
    );

    await save();

    const { request } = await waitForRequest('@patchHost');

    // The first row, generic-passive-host, is the one removed.
    expect((request.body as Record<string, unknown>).template_ids).toEqual([5]);
  });

  it('offers the templates but not the services toggle on a cloud platform', async () => {
    await initializeListing({ isCloudPlatform: true });

    await openCreation();

    expect(
      within(await screen.findByTestId('host-form-templates')).getByRole(
        'button',
        { name: labelAddNewEntry }
      )
    ).toBeInTheDocument();
    expect(
      screen.queryByLabelText(labelCreateServicesLinkedToTemplates)
    ).not.toBeInTheDocument();
  });
};
