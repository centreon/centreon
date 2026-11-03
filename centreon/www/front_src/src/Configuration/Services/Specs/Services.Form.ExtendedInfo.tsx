import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { labelInvalidGeographicCoordinates } from '../../ResourceForm/translatedLabels';
import initialize from './initialize';

const testIds = {
  actionUrl: 'service-form-extended-infos-actionUrl',
  altIcon: 'service-form-extended-infos-altIcon',
  categories: 'service-form-categories',
  comment: 'service-form-extended-infos-comment',
  geoCoordinates: 'service-form-extended-infos-geoCoordinates',
  graphTemplate: 'service-form-graph-template',
  icon: 'service-form-extended-infos-icon',
  iconPreview: 'service-form-extended-infos-icon-preview',
  note: 'service-form-extended-infos-note',
  noteUrl: 'service-form-extended-infos-noteUrl',
  severity: 'service-form-severity'
};

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllServices');
};

const openRow = async (name: string, alias: string): Promise<void> => {
  await userEvent.click(await screen.findByText(name));

  await waitForRequest(alias);
};

const openCreationForm = async (): Promise<void> => {
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

const clickClearIndicator = async (testId: string): Promise<void> => {
  // The clear indicator only shows on hover; userEvent clicks it anyway.
  await userEvent.click(
    screen
      .getByTestId(testId)
      .closest('.MuiAutocomplete-root')
      ?.querySelector('.MuiAutocomplete-clearIndicator') as HTMLElement
  );
};

// Without a name and a host the form cannot be saved.
const fillRequiredServiceFields = async (): Promise<void> => {
  await userEvent.type(await getInput('service-form-name'), 'cpu load');
  await userEvent.click(screen.getByTestId('service-form-host'));
  await pickOption('host B');
  await waitFor(() =>
    expect(screen.getByTestId('service-form-host')).toHaveValue('host B')
  );
  // Required on a cloud platform; elsewhere, it brings the check command a
  // service needs.
  await userEvent.click(screen.getByTestId('service-form-template'));
  await pickOption('generic-active-service');
  await waitFor(() =>
    expect(screen.getByTestId('service-form-template')).toHaveValue(
      'generic-active-service'
    )
  );
};

const save = async (): Promise<void> => {
  const button = await screen.findByTestId(panelDataTestIds.save);

  await waitFor(() => expect(button).toBeEnabled());
  await userEvent.click(button);
};

const getIconPreview = (): Element | null =>
  screen.getByTestId(testIds.iconPreview).querySelector('img');

export default () => {
  it('opens an existing service on its extended info', async () => {
    await initializeListing();

    await openRow('cpu', '@getService0');

    const note = await getInput(testIds.note);

    await waitFor(() => expect(note).toHaveValue('CPU load'));
    // Left out of the response, so still empty.
    expect(await getInput(testIds.noteUrl)).toHaveValue('');
    expect(await getInput(testIds.altIcon)).toHaveValue('');
    expect(await getInput(testIds.actionUrl)).toHaveValue(
      'https://example.com/actions/cpu'
    );
    expect(await getInput(testIds.geoCoordinates)).toHaveValue(
      '48.8566,2.3522'
    );
    expect(await getInput(testIds.comment)).toHaveValue(
      'Checked by the web team'
    );
    expect(screen.getByTestId(testIds.icon)).toHaveValue('cpu.png');
    await waitFor(() =>
      expect(getIconPreview()).toHaveAttribute('alt', 'cpu.png')
    );
    expect(screen.getByTestId(testIds.graphTemplate)).toHaveValue('CPU');
    // `name (level)`, as the legacy select shows a severity.
    expect(screen.getByTestId(testIds.severity)).toHaveValue('Major (2)');
    expect(
      within(
        screen
          .getByTestId(testIds.categories)
          .closest('.MuiAutocomplete-root') as HTMLElement
      ).getByText('Web')
    ).toBeVisible();
  });

  it('opens a service with no extended info on empty fields', async () => {
    await initializeListing();

    await openRow('memory', '@getService1');

    expect(await getInput(testIds.note)).toHaveValue('');
    expect(screen.getByTestId(testIds.icon)).toHaveValue('');
    expect(getIconPreview()).not.toBeInTheDocument();
    expect(screen.getByTestId(testIds.severity)).toHaveValue('');
    expect(screen.getByTestId(testIds.graphTemplate)).toHaveValue('');
  });

  it('shows each severity with its level, and by its name when it has none', async () => {
    await initializeListing();

    await openCreationForm();

    await userEvent.click(await screen.findByTestId(testIds.severity));
    await waitForRequest('@getFormServiceSeverities');

    const popper = await getPopper();

    expect(await within(popper).findByText('Major (2)')).toBeVisible();
    expect(within(popper).getByText('Critical (1)')).toBeVisible();
    expect(within(popper).getByText('Minor')).toBeVisible();
  });

  it('creates a service with its extended info', async () => {
    await initializeListing();

    await openCreationForm();
    await fillRequiredServiceFields();

    await userEvent.type(await getInput(testIds.note), '  CPU load  ');
    await userEvent.type(
      await getInput(testIds.noteUrl),
      'https://example.com/notes'
    );
    await userEvent.type(
      await getInput(testIds.actionUrl),
      'https://example.com/actions'
    );
    await userEvent.type(
      await getInput(testIds.geoCoordinates),
      '-33.8688,151.2093'
    );
    await userEvent.type(await getInput(testIds.altIcon), 'CPU');
    await userEvent.type(
      await getInput(testIds.comment),
      'Checked by the web team'
    );

    await userEvent.click(screen.getByTestId(testIds.icon));
    const { request: mediasRequest } = await waitForRequest('@getFormMedias');

    expect(mediasRequest.url.pathname).toContain(
      '/api/configuration/services/medias'
    );
    await pickOption('disk.png');
    await waitFor(() =>
      expect(getIconPreview()).toHaveAttribute('alt', 'disk.png')
    );

    await userEvent.click(screen.getByTestId(testIds.graphTemplate));
    const { request: graphTemplatesRequest } = await waitForRequest(
      '@getFormGraphTemplates'
    );

    expect(graphTemplatesRequest.url.pathname).toContain(
      '/api/configuration/services/graph_templates'
    );
    await pickOption('Memory');

    await userEvent.click(screen.getByTestId(testIds.severity));
    const { request: severitiesRequest } = await waitForRequest(
      '@getFormServiceSeverities'
    );

    expect(severitiesRequest.url.pathname).toContain(
      '/api/configuration/services/service_severities'
    );
    await pickOption('Critical (1)');
    await waitFor(() =>
      expect(screen.getByTestId(testIds.severity)).toHaveValue('Critical (1)')
    );

    await userEvent.click(screen.getByTestId(testIds.categories));
    const { request: categoriesRequest } = await waitForRequest(
      '@getFormServiceCategories'
    );

    expect(categoriesRequest.url.pathname).toContain(
      '/api/configuration/services/service_categories'
    );
    await pickOption('Database');

    await save();

    const { request } = await waitForRequest('@createService');
    const body = request.body as Record<string, unknown>;

    expect(body.extended_informations).toEqual({
      action_url: 'https://example.com/actions',
      alt_icon: 'CPU',
      comment: 'Checked by the web team',
      geo_coordinates: '-33.8688,151.2093',
      icon_id: 13,
      note: 'CPU load',
      note_url: 'https://example.com/notes'
    });
    expect(body.graph_template_id).toBe(8);
    expect(body.severity_id).toBe(2);
    expect(body.category_ids).toEqual([4]);
  });

  it('clears the extended info of a service', async () => {
    await initializeListing();

    await openRow('cpu', '@getService0');

    const note = await getInput(testIds.note);

    await waitFor(() => expect(note).toHaveValue('CPU load'));
    await userEvent.clear(note);
    const actionUrl = await getInput(testIds.actionUrl);

    // Blank is no value: sent as null.
    await userEvent.clear(actionUrl);
    await userEvent.type(actionUrl, '   ');
    await clickClearIndicator(testIds.icon);
    await waitFor(() => expect(getIconPreview()).not.toBeInTheDocument());
    await clickClearIndicator(testIds.severity);
    await clickClearIndicator(testIds.graphTemplate);

    await save();

    const { request } = await waitForRequest('@updateService0');
    const body = request.body as Record<string, unknown>;

    expect(body.extended_informations).toMatchObject({
      action_url: null,
      icon_id: null,
      note: null
    });
    expect(body.severity_id).toBe(null);
    expect(body.graph_template_id).toBe(null);
    // The categories the user sees are sent back as they were.
    expect(body.category_ids).toEqual([3]);
  });

  it('refuses geographic coordinates out of range', async () => {
    await initializeListing();

    await openCreationForm();

    await userEvent.type(await getInput(testIds.geoCoordinates), '91,2');
    await userEvent.tab();

    expect(
      await screen.findByText(labelInvalidGeographicCoordinates)
    ).toBeVisible();
  });

  it('refuses an alt icon longer than 200 characters', async () => {
    await initializeListing();

    await openCreationForm();

    await userEvent.click(await getInput(testIds.altIcon));
    await userEvent.paste('a'.repeat(201));
    await userEvent.tab();

    expect(
      await screen.findByText('Alt icon can be at most 200 characters')
    ).toBeVisible();
  });

  it('lets a user who may only look at services change no extended info', async () => {
    await initializeListing({ hasWriteAccess: false });

    await openRow('cpu', '@getService0');

    const note = await getInput(testIds.note);

    await waitFor(() => expect(note).toBeDisabled());
    expect(await getInput(testIds.comment)).toBeDisabled();
    expect(screen.getByTestId(testIds.icon)).toBeDisabled();
    expect(screen.getByTestId(testIds.severity)).toBeDisabled();
    expect(screen.getByTestId(testIds.graphTemplate)).toBeDisabled();
    expect(screen.getByTestId(testIds.categories)).toBeDisabled();
  });

  it('offers no graph template, alt icon nor comments on a cloud platform', async () => {
    await initializeListing({ isCloudPlatform: true });

    await openCreationForm();
    await fillRequiredServiceFields();

    await userEvent.type(await getInput(testIds.note), 'CPU load');

    expect(screen.getByTestId(testIds.icon)).toBeInTheDocument();
    expect(screen.getByTestId(testIds.severity)).toBeInTheDocument();
    expect(screen.getByTestId(testIds.categories)).toBeInTheDocument();
    [testIds.graphTemplate, testIds.altIcon, testIds.comment].forEach(
      (testId) => {
        expect(screen.queryByTestId(testId)).not.toBeInTheDocument();
      }
    );

    await save();

    const { request } = await waitForRequest('@createService');
    const body = request.body as Record<string, unknown>;

    // Refused by the API on cloud, so never sent.
    expect(body).not.toHaveProperty('graph_template_id');
    expect(body.extended_informations).not.toHaveProperty('alt_icon');
    expect(body.extended_informations).not.toHaveProperty('comment');
    expect(body.extended_informations).toMatchObject({ note: 'CPU load' });
  });
};
