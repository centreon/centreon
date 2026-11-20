import { type MockInstance, rs } from '@rstest/core';
import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { pick } from 'ramda';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelModalTitle,
  labelResourceUpdated
} from '../../ConfigurationBase/translatedLabels';
import {
  labelAddress,
  labelMonitoringServer,
  labelResolve
} from '../../Hosts/translatedLabels';
import { ResourceType } from '../../models';
import {
  labelMustBeAtMostCharacters,
  labelRequired
} from '../../ResourceForm/translatedLabels';
import { labelAlias, labelName } from '../translatedLabels';
import initialize, { initializeFormLayout } from './initialize';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllHostTemplates');
};

const openRow = async (name: string): Promise<void> => {
  await userEvent.click(await screen.findByText(name));
};

const openCreationForm = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId('add-resource'));
};

const getSaveButton = (): HTMLButtonElement | null =>
  document.querySelector<HTMLButtonElement>(
    `button[data-testid="${panelDataTestIds.save}"]`
  );

const save = async (): Promise<void> => {
  await waitFor(() => expect(getSaveButton()).toBeEnabled());
  await userEvent.click(getSaveButton() as HTMLButtonElement);
};

const expectHeader = async (text: string): Promise<void> => {
  const header = await screen.findByTestId(panelDataTestIds.header);

  await waitFor(() =>
    expect(header).toHaveTextContent(new RegExp(`^${text}$`))
  );
};

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId))[1] as HTMLInputElement;

const getName = (): Promise<HTMLInputElement> =>
  getInput('host-template-form-name');

const getAlias = (): Promise<HTMLInputElement> =>
  getInput('host-template-form-alias');

const leave = (element: HTMLElement): void => {
  act(() => {
    element.focus();
    element.blur();
  });
};

const getFieldError = (element: HTMLElement, message: string) =>
  within(element.closest('.MuiFormControl-root') as HTMLElement).findByText(
    message
  );

// The form's own routes: not the listing, nor a template's detail.
const getChoiceRouteCalls = (
  fetchSpy: MockInstance<typeof fetch>
): Array<string> =>
  fetchSpy.mock.calls
    .map(([input]) => (input instanceof Request ? input.url : String(input)))
    .filter((url) => /\/configuration\/host_templates\/[a-z_]/.test(url));

// The keys this section sends; the other sections' are theirs to assert.
const getIdentity = (body: unknown): Record<string, unknown> =>
  pick(['alias', 'name'], body as Record<string, unknown>);

const getWriteRequestCount = (): number =>
  [
    'createHostTemplate',
    'updateHostTemplate1',
    'updateHostTemplate2',
    'updateHostTemplate3'
  ].reduce((count, alias) => count + getRequestCalls(alias).length, 0);

// The form of a template that cannot be changed: every field disabled and
// nothing sent.
const expectReadOnly = async (): Promise<void> => {
  await waitFor(async () => expect(await getName()).toBeDisabled());
  expect(await getAlias()).toBeDisabled();
  expect(getWriteRequestCount()).toBe(0);
};

// The message as i18next words it with no catalogue loaded.
const getTooLongMessage = (label: string): string =>
  labelMustBeAtMostCharacters
    .replace('{{label}}', label)
    .replace('{{max}}', '200');

const expectNoSaveOrReset = (): void => {
  expect(getSaveButton()).not.toBeInTheDocument();
  expect(screen.queryByTestId(panelDataTestIds.reset)).not.toBeInTheDocument();
};

const getRect = (testId: string): DOMRect =>
  screen.getAllByTestId(testId)[0].getBoundingClientRect();

export default () => {
  describe('Panel: ', () => {
    it('opens the creation form in a side panel rather than a modal', async () => {
      await initializeListing();

      await openCreationForm();

      expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
      expect(screen.queryByTestId('Modal')).not.toBeInTheDocument();

      await expectHeader(
        labelModalTitle({ action: 'Add', type: ResourceType.HostTemplate })
      );
    });

    it('opens a template from its row on the detail endpoint', async () => {
      await initializeListing();

      await openRow('generic-active-host-custom');

      const { request } = await waitForRequest('@getHostTemplate1');

      // API Platform, not the legacy detail endpoint under `api/latest`.
      expect(request.url.pathname).toContain(
        '/api/configuration/host_templates/1'
      );
      expect(request.url.pathname).not.toContain('/api/latest');

      expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
      await expectHeader('generic-active-host-custom');
      await waitFor(() => expect(getSaveButton()).toBeInTheDocument());
    });

    it('opens the template a deep link names, in edit mode', async () => {
      initialize({ search: '?mode=edit&id=1' });

      const { request } = await waitForRequest('@getHostTemplate1');

      expect(request.url.pathname).toContain(
        '/api/configuration/host_templates/1'
      );

      expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
      // Named by the detail response, there being no row to take it from.
      await expectHeader('generic-active-host-custom');
      await waitFor(async () =>
        expect(await getName()).toHaveValue('generic-active-host-custom')
      );
    });

    it('opens a parent template from the link in its child row', async () => {
      await initializeListing();

      // Both the first and the last row inherit from it.
      const [link] = await screen.findAllByTestId('host-template-link_2');

      await userEvent.click(link);

      await waitForRequest('@getHostTemplate2');

      await expectHeader('generic-active-host');
      await waitFor(async () =>
        expect(await getName()).toHaveValue('generic-active-host')
      );
    });

    it('opens a template read-only for a user who may only look at templates', async () => {
      const fetchSpy = rs.spyOn(window, 'fetch');

      await initializeListing({ hasWriteAccess: false });

      await openRow('generic-active-host-custom');
      await waitForRequest('@getHostTemplate1');

      await expectHeader('generic-active-host-custom');
      await waitFor(async () =>
        expect(await getName()).toHaveValue('generic-active-host-custom')
      );
      await expectReadOnly();
      expectNoSaveOrReset();
      // Those routes answer 403 without write access.
      expect(getChoiceRouteCalls(fetchSpy)).toEqual([]);

      fetchSpy.mockRestore();
    });

    it('opens a locked template read-only from its row, without delete or duplicate', async () => {
      await initializeListing();

      await openRow('OS-Linux-SNMP-custom');
      await waitForRequest('@getHostTemplate3');

      await expectHeader('OS-Linux-SNMP-custom');
      await waitFor(async () =>
        expect(await getName()).toHaveValue('OS-Linux-SNMP-custom')
      );
      await expectReadOnly();
      expectNoSaveOrReset();
      expect(
        screen.queryByTestId(panelDataTestIds.delete)
      ).not.toBeInTheDocument();
      expect(
        screen.queryByTestId(panelDataTestIds.duplicate)
      ).not.toBeInTheDocument();
    });

    it('opens a locked template read-only from a deep link', async () => {
      initialize({ search: '?mode=edit&id=3' });

      await waitForRequest('@getHostTemplate3');

      await waitFor(async () =>
        expect(await getName()).toHaveValue('OS-Linux-SNMP-custom')
      );
      await expectReadOnly();
      expectNoSaveOrReset();
    });

    it('drops Save and Reset when the panel moves from a template to a locked one', async () => {
      await initializeListing();

      await openRow('generic-active-host-custom');
      await waitForRequest('@getHostTemplate1');
      await waitFor(() => expect(getSaveButton()).toBeInTheDocument());

      await openRow('OS-Linux-SNMP-custom');
      await waitForRequest('@getHostTemplate3');

      await waitFor(async () =>
        expect(await getName()).toHaveValue('OS-Linux-SNMP-custom')
      );
      await expectReadOnly();
      expectNoSaveOrReset();
    });

    it('brings Save back when the panel moves from a locked template to another one', async () => {
      await initializeListing();
      await openRow('OS-Linux-SNMP-custom');
      await waitForRequest('@getHostTemplate3');
      await waitFor(async () =>
        expect(await getName()).toHaveValue('OS-Linux-SNMP-custom')
      );
      expectNoSaveOrReset();
      await openRow('generic-active-host-custom');
      await waitForRequest('@getHostTemplate1');
      await waitFor(() => expect(getSaveButton()).toBeInTheDocument());
      expect(await getName()).toBeEnabled();
    });
  });

  describe('General information: ', () => {
    it('opens a template on its name and alias', async () => {
      await initializeListing();

      await openRow('generic-active-host-custom');
      await waitForRequest('@getHostTemplate1');

      await waitFor(async () =>
        expect(await getName()).toHaveValue('generic-active-host-custom')
      );
      expect(await getAlias()).toHaveValue('Generic active host');
      expect(await getName()).toBeEnabled();
    });

    it('opens a template the detail sends no alias for, on an empty alias', async () => {
      initialize({ search: '?mode=edit&id=2' });

      await waitForRequest('@getHostTemplate2');

      await waitFor(async () =>
        expect(await getName()).toHaveValue('generic-active-host')
      );
      expect(await getAlias()).toHaveValue('');
    });

    it('shows none of the fields only a host has', async () => {
      await initializeListing();
      await openCreationForm();

      await getName();

      expect(screen.queryByLabelText(labelAddress)).not.toBeInTheDocument();
      expect(
        screen.queryByLabelText(labelMonitoringServer)
      ).not.toBeInTheDocument();
      expect(screen.queryByText(labelResolve)).not.toBeInTheDocument();
    });

    it('creates a template, its name as the server stores it', async () => {
      await initializeListing();
      await openCreationForm();

      await userEvent.type(await getName(), '  my generic  host ');
      await userEvent.type(await getAlias(), '  My generic host ');

      await save();

      const { request } = await waitForRequest('@createHostTemplate');

      expect(request.method).toBe('POST');
      expect(getIdentity(request.body)).toEqual({
        alias: 'My generic host',
        name: 'my_generic__host'
      });
    });

    it('updates a template with the whole form', async () => {
      await initializeListing();

      await openRow('generic-active-host-custom');
      await waitForRequest('@getHostTemplate1');

      const alias = await getAlias();

      await waitFor(() => expect(alias).toHaveValue('Generic active host'));
      await userEvent.clear(alias);
      await userEvent.type(alias, 'Generic host');

      await save();

      const { request } = await waitForRequest('@updateHostTemplate1');

      expect(request.method).toBe('PATCH');
      expect(getIdentity(request.body)).toEqual({
        alias: 'Generic host',
        name: 'generic-active-host-custom'
      });
      expect(request.body).not.toHaveProperty('is_locked');
      expect(
        await screen.findByText(labelResourceUpdated('Host template'))
      ).toBeInTheDocument();
    });

    it('asks for a name and an alias', async () => {
      await initializeListing();
      await openCreationForm();

      const name = await getName();
      const alias = await getAlias();

      leave(name);
      leave(alias);

      expect(await getFieldError(name, labelRequired)).toBeVisible();
      expect(await getFieldError(alias, labelRequired)).toBeVisible();
      expect(getSaveButton()).toBeDisabled();
    });

    it('asks for an alias made of more than blanks', async () => {
      await initializeListing();
      await openCreationForm();

      await userEvent.type(await getName(), 'generic');
      const alias = await getAlias();

      await userEvent.type(alias, '   ');
      leave(alias);

      expect(await getFieldError(alias, labelRequired)).toBeVisible();
      expect(getSaveButton()).toBeDisabled();
    });

    it('refuses a name or an alias longer than 200 characters', async () => {
      await initializeListing();
      await openCreationForm();

      const name = await getName();
      const alias = await getAlias();

      await userEvent.click(name);
      await userEvent.paste('n'.repeat(201));
      await userEvent.click(alias);
      await userEvent.paste('a'.repeat(201));
      leave(alias);

      expect(
        await getFieldError(name, getTooLongMessage(labelName))
      ).toBeVisible();
      expect(
        await getFieldError(alias, getTooLongMessage(labelAlias))
      ).toBeVisible();
    });

    it('accepts a name the host form would refuse', async () => {
      await initializeListing();
      await openCreationForm();

      await userEvent.type(await getName(), '_Module_cpu|load');
      await userEvent.type(await getAlias(), 'CPU');

      await save();

      const { request } = await waitForRequest('@createHostTemplate');

      expect(getIdentity(request.body)).toEqual({
        alias: 'CPU',
        name: '_Module_cpu|load'
      });
    });
  });

  describe('Layout: ', () => {
    it.each(['narrow', 'wide'] as const)(
      'shows the name and the alias side by side, equally wide (%s)',
      async (width) => {
        initializeFormLayout({ width });

        await waitFor(() =>
          expect(
            screen.getAllByTestId('host-template-form-alias')
          ).toHaveLength(2)
        );

        const name = getRect('host-template-form-name');
        const alias = getRect('host-template-form-alias');

        expect(alias.left).toBeGreaterThanOrEqual(name.right);
        expect(alias.top).toBe(name.top);
        expect(alias.width).toBe(name.width);
      }
    );
  });
};
