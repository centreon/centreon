import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelChildHosts,
  labelHostGroups,
  labelParentHosts
} from '../../Hosts/translatedLabels';
import initialize, { initializeFormLayout } from './initialize';

const testIds = {
  categories: 'host-template-form-categories',
  linkedServiceTemplates: 'host-template-form-linked-service-templates',
  severity: 'host-template-form-severity'
};

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// Scoped to the popper: the listing behind the panel shows the same names.
const pickOption = async (option: string): Promise<void> => {
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
};

const getChips = (testId: string): Array<string | null> =>
  Array.from(
    screen
      .getByTestId(testId)
      .closest('.MuiAutocomplete-root')
      ?.querySelectorAll('.MuiChip-root') ?? []
  ).map(({ textContent }) => textContent);

const save = async (): Promise<void> => {
  const button = document.querySelector(
    `button[data-testid="${panelDataTestIds.save}"]`
  ) as HTMLButtonElement;

  await waitFor(() => expect(button).toBeEnabled());
  await userEvent.click(button);
};

const openCreationForm = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

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
  describe('Relations: ', () => {
    it('opens a template on its host categories and severity', async () => {
      await openTemplate(1);

      await waitFor(() =>
        expect(getChips(testIds.categories)).toEqual(['Virtual'])
      );
      expect(screen.getByTestId(testIds.severity)).toHaveValue('Minor');
    });

    it('creates a template with categories and a severity read from the host template routes', async () => {
      await openCreationForm();

      await userEvent.click(screen.getByTestId(testIds.categories));
      const { request: categoriesRequest } = await waitForRequest(
        '@getFormHostCategories'
      );

      expect(categoriesRequest.url.pathname).toContain(
        '/api/configuration/host_templates/host_categories'
      );
      await pickOption('Physical');

      await userEvent.click(screen.getByTestId(testIds.severity));
      const { request: severitiesRequest } = await waitForRequest(
        '@getFormHostSeverities'
      );

      expect(severitiesRequest.url.pathname).toContain(
        '/api/configuration/host_templates/host_severities'
      );
      await pickOption('Critical');

      await save();

      const { request } = await waitForRequest('@createHostTemplate');
      const body = request.body as Record<string, unknown>;

      expect(body.category_ids).toEqual([5]);
      expect(body.severity_id).toBe(1);
    });

    it('creates a template with no category and no severity', async () => {
      await openCreationForm();

      await save();

      const { request } = await waitForRequest('@createHostTemplate');
      const body = request.body as Record<string, unknown>;

      expect(body.category_ids).toEqual([]);
      expect(body.severity_id).toBeNull();
    });

    it('shows none of the relations only a host has', async () => {
      await openTemplate(1);

      await waitFor(() =>
        expect(getChips(testIds.categories)).toEqual(['Virtual'])
      );
      expect(screen.queryByLabelText(labelHostGroups)).not.toBeInTheDocument();
      expect(screen.queryByLabelText(labelParentHosts)).not.toBeInTheDocument();
      expect(screen.queryByLabelText(labelChildHosts)).not.toBeInTheDocument();
    });

    it('opens a template on its linked service templates', async () => {
      await openTemplate(1);

      await waitFor(() =>
        expect(getChips(testIds.linkedServiceTemplates)).toEqual([
          'generic-ping'
        ])
      );
    });

    it.each([
      ['on-premise', false],
      ['cloud', true]
    ])(
      'creates a template linked to service templates read from the host template route (%s)',
      async (_platform, isCloudPlatform) => {
        await openCreationForm({ isCloudPlatform });

        await userEvent.click(
          screen.getByTestId(testIds.linkedServiceTemplates)
        );
        const { request: serviceTemplatesRequest } = await waitForRequest(
          '@getFormServiceTemplates'
        );

        expect(serviceTemplatesRequest.url.pathname).toContain(
          '/api/configuration/host_templates/service_templates'
        );
        await pickOption('generic-cpu');

        await save();

        const { request } = await waitForRequest('@createHostTemplate');

        expect(
          (request.body as Record<string, unknown>).linked_service_template_ids
        ).toEqual([32]);
      }
    );

    it('unlinks every service template of a template', async () => {
      await openTemplate(1);

      await waitFor(() =>
        expect(getChips(testIds.linkedServiceTemplates)).toEqual([
          'generic-ping'
        ])
      );
      await userEvent.click(
        screen
          .getByTestId(testIds.linkedServiceTemplates)
          .closest('.MuiAutocomplete-root')
          ?.querySelector('.MuiChip-deleteIcon') as Element
      );

      await save();

      const { request } = await waitForRequest('@updateHostTemplate1');

      expect(
        (request.body as Record<string, unknown>).linked_service_template_ids
      ).toEqual([]);
    });

    it.each(['narrow', 'wide'] as const)(
      'shows the linked service templates left of the host categories (%s)',
      async (width) => {
        initializeFormLayout({ width });

        await waitFor(() =>
          expect(screen.getByTestId(testIds.categories)).toBeInTheDocument()
        );

        const linked = screen
          .getByTestId(testIds.linkedServiceTemplates)
          .getBoundingClientRect();
        const categories = screen
          .getByTestId(testIds.categories)
          .getBoundingClientRect();

        expect(categories.left).toBeGreaterThanOrEqual(linked.right);
        expect(categories.top).toBe(linked.top);
      }
    );

    it('opens a template with its relations read-only for a user who may only look at templates', async () => {
      initialize({ hasWriteAccess: false, search: '?mode=edit&id=1' });

      await waitForRequest('@getHostTemplate1');

      await waitFor(() =>
        expect(getChips(testIds.categories)).toEqual(['Virtual'])
      );
      expect(screen.getByTestId(testIds.categories)).toBeDisabled();
      expect(screen.getByTestId(testIds.severity)).toHaveValue('Minor');
      expect(screen.getByTestId(testIds.severity)).toBeDisabled();
      expect(getChips(testIds.linkedServiceTemplates)).toEqual([
        'generic-ping'
      ]);
      expect(screen.getByTestId(testIds.linkedServiceTemplates)).toBeDisabled();
    });

    it('opens a locked template with its relations read-only', async () => {
      await openTemplate(3);

      await waitFor(() =>
        expect(screen.getByTestId(testIds.severity)).toBeDisabled()
      );
      expect(screen.getByTestId(testIds.categories)).toBeDisabled();
      expect(screen.getByTestId(testIds.linkedServiceTemplates)).toBeDisabled();
    });
  });
};
