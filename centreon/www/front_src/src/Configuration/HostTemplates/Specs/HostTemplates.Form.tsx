import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelDataProcessing,
  labelHostConfiguration,
  labelHostExtendedInfos,
  labelLinkedServiceTemplates,
  labelNotification,
  labelRelations,
  labelRequired
} from '../../Hosts/translatedLabels';
import initialize from './initialize';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHostTemplates');
};

const openRow = async (name: string): Promise<void> => {
  await userEvent.click(await screen.findByText(name));
};

const openCreation = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId('add-resource'));
};

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const getPopper = async (): Promise<HTMLElement> => {
  await waitFor(() =>
    expect(document.querySelector('.MuiAutocomplete-popper')).not.toBeNull()
  );

  return Array.from(
    document.querySelectorAll<HTMLElement>('.MuiAutocomplete-popper')
  ).at(-1) as HTMLElement;
};

const pickOption = async (label: string): Promise<void> => {
  await userEvent.click(await within(await getPopper()).findByText(label));
};

const save = async (): Promise<void> => {
  await userEvent.click(
    document.querySelector(
      `button[data-testid="${panelDataTestIds.save}"]`
    ) as HTMLButtonElement
  );
};

// What only a host has: none of it on a template.
const hostOnlyTestIds = [
  'host-form-address',
  'host-form-address-resolve',
  'host-form-poller',
  'host-form-create-services-linked-to-templates',
  'host-form-groups',
  'host-form-parent-hosts',
  'host-form-child-hosts',
  'host-form-extended-infos-geoCoordinates'
];

export default () => {
  describe('Form: ', () => {
    it('opens the creation form with the five sections of the host form', async () => {
      await initializeListing();

      await openCreation();

      await waitFor(async () =>
        expect(
          await screen.findByTestId(panelDataTestIds.header)
        ).toHaveTextContent(/^Add a host template$/)
      );

      for (const section of [
        labelHostConfiguration,
        labelNotification,
        labelRelations,
        labelDataProcessing,
        labelHostExtendedInfos
      ]) {
        expect((await screen.findAllByText(section))[0]).toBeVisible();
      }
    });

    it('shows none of the fields only a host has', async () => {
      await initializeListing();

      await openCreation();

      await getInput('host-form-name');

      hostOnlyTestIds.forEach((testId) => {
        expect(screen.queryByTestId(testId)).not.toBeInTheDocument();
      });
      expect(screen.getByTestId('host-form-templates')).toBeInTheDocument();
      expect(
        screen.getByTestId('host-form-service-templates')
      ).toBeInTheDocument();
      expect(screen.getByTestId('host-form-categories')).toBeInTheDocument();
    });

    it('requires an alias, as legacy does for a template', async () => {
      await initializeListing();

      await openCreation();

      await userEvent.type(await getInput('host-form-name'), 'my-template');
      await userEvent.click(await getInput('host-form-alias'));
      await userEvent.tab();

      expect(await screen.findByText(labelRequired)).toBeVisible();
    });

    it('accepts a name starting with _Module_, a prefix only hosts reserve', async () => {
      await initializeListing();

      await openCreation();

      await userEvent.type(await getInput('host-form-name'), '_Module_tpl');
      await userEvent.type(await getInput('host-form-alias'), 'A template');
      await save();

      const { request } = await waitForRequest('@createHostTemplate');

      expect((request.body as Record<string, unknown>).name).toBe(
        '_Module_tpl'
      );
    });

    it('creates a template without any field only a host has', async () => {
      await initializeListing();

      await openCreation();

      await userEvent.type(await getInput('host-form-name'), 'my-template');
      await userEvent.type(await getInput('host-form-alias'), 'My template');

      await userEvent.click(screen.getByTestId('host-form-service-templates'));
      const { request: serviceTemplatesRequest } = await waitForRequest(
        '@getFormServiceTemplates'
      );

      expect(serviceTemplatesRequest.url.pathname).toContain(
        '/api/configuration/host_templates/service_templates'
      );
      await pickOption('OS-Linux-CPU');

      await save();

      const { request } = await waitForRequest('@createHostTemplate');
      const body = request.body as Record<string, unknown>;

      expect(request.url.pathname).toContain(
        '/api/configuration/host_templates'
      );
      expect(request.url.pathname).not.toContain('/api/latest');
      expect(body).toMatchObject({
        alias: 'My template',
        category_ids: [],
        name: 'my-template',
        service_template_ids: [32],
        template_ids: []
      });
      [
        'address',
        'poller_id',
        'create_services_linked_to_templates',
        'host_group_ids',
        'parent_host_ids',
        'child_host_ids'
      ].forEach((key) => {
        expect(body).not.toHaveProperty(key);
      });
      expect(body.extended_informations).not.toHaveProperty('geo_coordinates');
    });

    it('opens an existing template on the values the detail endpoint returns', async () => {
      await initializeListing();

      await openRow('Generic active host');

      const { request } = await waitForRequest('@getHostTemplate5');

      expect(request.url.pathname).toContain(
        '/api/configuration/host_templates/5'
      );
      expect(request.url.pathname).not.toContain('/api/latest');

      const name = await getInput('host-form-name');

      await waitFor(() =>
        expect(name).toHaveValue('template 5 as the detail endpoint spells it')
      );
      expect(await getInput('host-form-alias')).toHaveValue(
        'alias of template 5 as the detail endpoint spells it'
      );
      await waitFor(() => {
        const chips = (
          screen
            .getByLabelText(labelLinkedServiceTemplates)
            .closest('.MuiAutocomplete-root') as Element
        ).querySelectorAll('.MuiChip-root');

        expect(chips).toHaveLength(1);
        expect(chips[0]).toHaveTextContent(/^Base-Ping-LAN$/);
      });
      expect(name).toBeEnabled();
    });

    it('saves an edited template through a partial update', async () => {
      await initializeListing();

      await openRow('Generic active host');

      const alias = await getInput('host-form-alias');

      await waitFor(() =>
        expect(alias).toHaveValue(
          'alias of template 5 as the detail endpoint spells it'
        )
      );
      await userEvent.clear(alias);
      await userEvent.type(alias, 'Renamed');

      await save();

      const { request } = await waitForRequest('@patchHostTemplate5');
      const body = request.body as Record<string, unknown>;

      expect(request.url.pathname).toContain(
        '/api/configuration/host_templates/5'
      );
      expect(body.alias).toBe('Renamed');
      expect(body).not.toHaveProperty('poller_id');
    });

    it('opens a locked template read-only', async () => {
      await initializeListing();

      await openRow('Linux SNMP');

      await waitForRequest('@getHostTemplate8');

      const name = await getInput('host-form-name');

      await waitFor(() =>
        expect(name).toHaveValue('template 5 as the detail endpoint spells it')
      );
      expect(name).toBeDisabled();
      expect(await getInput('host-form-alias')).toBeDisabled();
    });

    it('has no notification section on cloud, as the host form', async () => {
      await initializeListing({ isCloudPlatform: true });

      await openCreation();

      await getInput('host-form-name');

      expect(screen.queryByText(labelNotification)).not.toBeInTheDocument();
      expect(screen.getAllByText(labelLinkedServiceTemplates)[0]).toBeVisible();
    });
  });
};
