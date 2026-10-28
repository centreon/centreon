import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import { labelServiceInvalidGeographicCoordinates } from '../translatedLabels';
import { fillRequiredServiceFields } from './formHelpers';
import initialize from './initialize';

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

const save = async (): Promise<void> => {
  const button = await screen.findByTestId(panelDataTestIds.save);

  await waitFor(() => expect(button).toBeEnabled());
  await userEvent.click(button);
};

const getIconPreview = (): Element | null =>
  screen.getByTestId('service-form-icon-preview').querySelector('img');

export default () => {
  it('opens an existing service on its extended info', async () => {
    await initializeListing();

    await openRow('cpu', '@getService0');

    const note = await getInput('service-form-note');

    await waitFor(() => expect(note).toHaveValue('CPU load'));
    // Left out of the response, so still empty.
    expect(await getInput('service-form-note-url')).toHaveValue('');
    expect(await getInput('service-form-alt-icon')).toHaveValue('');
    expect(await getInput('service-form-action-url')).toHaveValue(
      'https://example.com/actions/cpu'
    );
    expect(await getInput('service-form-geo-coordinates')).toHaveValue(
      '48.8566,2.3522'
    );
    expect(await getInput('service-form-comments')).toHaveValue(
      'Checked by the web team'
    );
    expect(screen.getByTestId('service-form-icon')).toHaveValue('cpu.png');
    await waitFor(() =>
      expect(getIconPreview()).toHaveAttribute('alt', 'cpu.png')
    );
    expect(screen.getByTestId('service-form-graph-template')).toHaveValue(
      'CPU'
    );
    // `name (level)`, as the legacy select shows a severity.
    expect(screen.getByTestId('service-form-severity')).toHaveValue(
      'Major (2)'
    );
    expect(
      within(
        screen
          .getByTestId('service-form-categories')
          .closest('.MuiAutocomplete-root') as HTMLElement
      ).getByText('Web')
    ).toBeVisible();
  });

  it('opens a service with no extended info on empty fields', async () => {
    await initializeListing();

    await openRow('memory', '@getService1');

    expect(await getInput('service-form-note')).toHaveValue('');
    expect(screen.getByTestId('service-form-icon')).toHaveValue('');
    expect(getIconPreview()).not.toBeInTheDocument();
    expect(screen.getByTestId('service-form-severity')).toHaveValue('');
    expect(screen.getByTestId('service-form-graph-template')).toHaveValue('');
  });

  it('creates a service with its extended info', async () => {
    await initializeListing();

    await openCreationForm();
    // Without a name and a host the form cannot be saved.
    await fillRequiredServiceFields();

    await userEvent.type(await getInput('service-form-note'), '  CPU load  ');
    await userEvent.type(
      await getInput('service-form-note-url'),
      'https://example.com/notes'
    );
    await userEvent.type(
      await getInput('service-form-action-url'),
      'https://example.com/actions'
    );
    await userEvent.type(
      await getInput('service-form-geo-coordinates'),
      '-33.8688,151.2093'
    );
    await userEvent.type(await getInput('service-form-alt-icon'), 'CPU');
    await userEvent.type(
      await getInput('service-form-comments'),
      'Checked by the web team'
    );

    await userEvent.click(screen.getByTestId('service-form-icon'));
    const { request: mediasRequest } = await waitForRequest('@getFormMedias');

    expect(mediasRequest.url.pathname).toContain(
      '/api/configuration/services/medias'
    );
    await pickOption('disk.png');
    await waitFor(() =>
      expect(getIconPreview()).toHaveAttribute('alt', 'disk.png')
    );

    await userEvent.click(screen.getByTestId('service-form-graph-template'));
    const { request: graphTemplatesRequest } = await waitForRequest(
      '@getFormGraphTemplates'
    );

    expect(graphTemplatesRequest.url.pathname).toContain(
      '/api/configuration/services/graph_templates'
    );
    await pickOption('Memory');

    await userEvent.click(screen.getByTestId('service-form-severity'));
    const { request: severitiesRequest } = await waitForRequest(
      '@getFormServiceSeverities'
    );

    expect(severitiesRequest.url.pathname).toContain(
      '/api/configuration/services/service_severities'
    );
    await pickOption('Critical (1)');

    await userEvent.click(screen.getByTestId('service-form-categories'));
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

    const note = await getInput('service-form-note');

    await waitFor(() => expect(note).toHaveValue('CPU load'));
    await userEvent.clear(note);
    const actionUrl = await getInput('service-form-action-url');

    // Blank is no value: sent as null.
    await userEvent.clear(actionUrl);
    await userEvent.type(actionUrl, '   ');
    await clickClearIndicator('service-form-icon');
    await waitFor(() => expect(getIconPreview()).not.toBeInTheDocument());
    await clickClearIndicator('service-form-severity');
    await clickClearIndicator('service-form-graph-template');

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

    await userEvent.type(
      await getInput('service-form-geo-coordinates'),
      '91,2'
    );
    await userEvent.tab();

    expect(
      await screen.findByText(labelServiceInvalidGeographicCoordinates)
    ).toBeVisible();
  });

  it('refuses an alt icon longer than 200 characters', async () => {
    await initializeListing();

    await openCreationForm();

    await userEvent.click(await getInput('service-form-alt-icon'));
    await userEvent.paste('a'.repeat(201));
    await userEvent.tab();

    expect(
      await screen.findByText('Alt icon can be at most 200 characters')
    ).toBeVisible();
  });

  it('lets a user who may only look at services change no extended info', async () => {
    await initializeListing({ hasWriteAccess: false });

    await openRow('cpu', '@getService0');

    const note = await getInput('service-form-note');

    await waitFor(() => expect(note).toBeDisabled());
    expect(await getInput('service-form-comments')).toBeDisabled();
    expect(screen.getByTestId('service-form-icon')).toBeDisabled();
    expect(screen.getByTestId('service-form-severity')).toBeDisabled();
    expect(screen.getByTestId('service-form-graph-template')).toBeDisabled();
    expect(screen.getByTestId('service-form-categories')).toBeDisabled();
  });

  it('offers no graph template, alt icon nor comments on a cloud platform', async () => {
    await initializeListing({ isCloudPlatform: true });

    await openCreationForm();
    // On a cloud platform a template is required too.
    await fillRequiredServiceFields();

    await userEvent.type(await getInput('service-form-note'), 'CPU load');

    expect(screen.getByTestId('service-form-icon')).toBeInTheDocument();
    expect(screen.getByTestId('service-form-severity')).toBeInTheDocument();
    expect(screen.getByTestId('service-form-categories')).toBeInTheDocument();
    [
      'service-form-graph-template',
      'service-form-alt-icon',
      'service-form-comments'
    ].forEach((testId) => {
      expect(screen.queryByTestId(testId)).not.toBeInTheDocument();
    });

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
