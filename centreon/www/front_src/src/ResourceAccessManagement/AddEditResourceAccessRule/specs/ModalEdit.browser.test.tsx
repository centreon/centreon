import { SnackbarProvider } from '@centreon/ui';
import {
  type PlatformVersions,
  platformVersionsAtom
} from '@centreon/ui-context';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, Method, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';

import {
  editedResourceAccessRuleIdAtom,
  modalStateAtom,
  resourceAccessRulesNamesAtom
} from '../../atom';
import { ModalMode } from '../../models';
import {
  labelActiveOrInactive,
  labelAddFilter,
  labelAddNewDataset,
  labelAddResourceDatasets,
  labelAllBusinessViews,
  labelAllContactGroups,
  labelAllContacts,
  labelAllHostGroups,
  labelAllHostGroupsSelected,
  labelAllResourcesSelected,
  labelBusinessView,
  labelContactGroups,
  labelContacts,
  labelContactsAndContactGroups,
  labelDelete,
  labelDescription,
  labelDoYouWantToQuitWithoutSaving,
  labelEditResourceAccessRule,
  labelExit,
  labelName,
  labelNameAlreadyExists,
  labelRequired,
  labelResourceAccessRuleEditedSuccess,
  labelRuleProperies,
  labelSave,
  labelSelectResource,
  labelSelectResourceType,
  labelYourFormHasUnsavedChanges
} from '../../translatedLabels';
import { AddEditResourceAccessRuleModal } from '..';
import {
  findBusinessViewsEndpoint,
  resourceAccessRuleEndpoint
} from '../api/endpoints';
import {
  editedRuleFormData,
  editedRuleFormDataiWithAllBusinessViews,
  editedRuleFormDataiWithBusinessViews,
  editedRuleFormDataWithAllContactsAndContactGroups,
  findBusinessViewsResponse,
  findResourceAccessRuleResponseDecoded,
  platformVersions
} from './testUtils';

interface InitializeProps {
  // Set when the BAM module is installed, which offers the business views.
  withBusinessViews?: boolean;
}

const initialize = ({
  withBusinessViews = false
}: InitializeProps = {}): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();
  store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Edit });
  store.set(editedResourceAccessRuleIdAtom, 1);
  store.set(resourceAccessRulesNamesAtom, [
    { id: 1, name: 'Rule 1' },
    { id: 2, name: 'Rule 2' }
  ]);

  if (withBusinessViews) {
    store.set(platformVersionsAtom, platformVersions as PlatformVersions);
  }

  interceptAPIRequest({
    alias: 'editResourceAccessRuleRequest',
    method: Method.PUT,
    path: resourceAccessRuleEndpoint({ id: 1 }),
    response: { status: 'ok' }
  });

  interceptAPIRequest({
    alias: 'findBusinessViewsEndpoint',
    method: Method.GET,
    path: `${findBusinessViewsEndpoint}**`,
    response: findBusinessViewsResponse
  });

  // A client of its own, so no cache leaks from one test to the next. The
  // edited rule has no observer: it must not be garbage collected.
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { gcTime: Number.POSITIVE_INFINITY, retry: false }
    }
  });
  // The listing puts the edited rule in the query cache, under the key the
  // form reads its initial values from, before the modal opens.
  queryClient.setQueryData(
    ['resource-access-rule', 1],
    findResourceAccessRuleResponseDecoded()
  );

  render(
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <QueryClientProvider client={queryClient}>
          <SnackbarProvider>
            <AddEditResourceAccessRuleModal />
          </SnackbarProvider>
        </QueryClientProvider>
      </Provider>
    </div>
  );
};

const getLast = <T,>(elements: Array<T>): T => elements[elements.length - 1];

// The label is on the select's wrapper: the menu opens from the combobox in
// it, and the selected type is the value of its hidden input.
const openResourceTypeSelect = async (select: HTMLElement): Promise<void> => {
  await userEvent.click(
    select.querySelector('[role="combobox"]') as HTMLElement
  );
};

const getResourceTypes = (): Array<string> =>
  screen
    .getAllByLabelText(labelSelectResourceType)
    .map((select) => (select.querySelector('input') as HTMLInputElement).value);

const selectLastResourceType = async (type: string): Promise<void> => {
  await openResourceTypeSelect(
    getLast(await screen.findAllByLabelText(labelSelectResourceType))
  );
  await userEvent.click(await screen.findByRole('option', { name: type }));
};

// `@centreon/ui` autocompletes put the same test id on the wrapper and the input.
const getResourceInputs = (): Array<HTMLElement> =>
  screen
    .getAllByTestId(labelSelectResource)
    .filter((element) => element.tagName === 'INPUT');

const openLastResources = async (): Promise<void> => {
  await userEvent.click(
    getLast(await screen.findAllByTestId(labelSelectResource))
  );
};

// The rule has two datasets: host group + host, and services.
const deleteLastDataset = async (): Promise<void> => {
  await userEvent.click(
    getLast(await screen.findAllByTestId('DeleteOutlineIcon'))
  );

  await waitFor(() => {
    expect(getResourceTypes()).toEqual(['hostgroup', 'host']);
  });
};

// Leaves the host group filter alone in the remaining dataset.
const deleteLastFilter = async (): Promise<void> => {
  await userEvent.click(getLast(screen.getAllByTestId(labelDelete)));

  await waitFor(() => {
    expect(getResourceTypes()).toEqual(['hostgroup']);
  });
};

const replaceName = async (name: string): Promise<void> => {
  const nameInput = screen.getByLabelText(labelName);

  await userEvent.clear(nameInput);
  await userEvent.type(nameInput, name);
};

// The selected resources, contacts and contact groups are shown as chips.
const getChips = (): Array<string | null> =>
  Array.from(
    document.querySelectorAll('.MuiChip-label'),
    (chip) => chip.textContent
  );

const clickSave = async (): Promise<void> => {
  await waitFor(() => {
    expect(screen.getByLabelText(labelSave)).toBeEnabled();
  });
  await userEvent.click(screen.getByLabelText(labelSave));
};

const expectSaved = async (): Promise<void> => {
  expect(
    await screen.findByText(labelResourceAccessRuleEditedSuccess)
  ).toBeVisible();
  await waitFor(() => {
    expect(
      screen.queryByText(labelEditResourceAccessRule)
    ).not.toBeInTheDocument();
  });
};

// A click on the backdrop, around the modal.
const clickOutsideModal = async (): Promise<void> => {
  await userEvent.click(
    document.querySelector('.MuiDialog-container') as HTMLElement
  );
};

const expectConfirmationDialog = async (): Promise<void> => {
  const dialog = (
    await screen.findByText(labelYourFormHasUnsavedChanges)
  ).closest('[role="dialog"]') as HTMLElement;

  expect(screen.getByText(labelYourFormHasUnsavedChanges)).toBeVisible();
  expect(screen.getByText(labelDoYouWantToQuitWithoutSaving)).toBeVisible();
  expect(within(dialog).getByRole('button', { name: 'Cancel' })).toBeEnabled();
  expect(within(dialog).getByRole('button', { name: 'Confirm' })).toBeEnabled();
  // The modal stays open behind the dialog.
  expect(screen.getByText(labelEditResourceAccessRule)).toBeInTheDocument();
};

describe('Edit modal', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the edit resource access rule modal and control actions', async () => {
    initialize();

    expect(await screen.findByText(labelEditResourceAccessRule)).toBeVisible();
    expect(screen.getByText(labelRuleProperies)).toBeVisible();
    expect(screen.getByText(labelAddResourceDatasets)).toBeVisible();
    expect(screen.getByText(labelContactsAndContactGroups)).toBeVisible();
    expect(screen.getByLabelText(labelExit)).toBeEnabled();
    expect(screen.getByLabelText(labelSave)).toBeDisabled();

    expect(screen.getByLabelText(labelAllContacts)).not.toBeChecked();
    expect(screen.getByLabelText(labelAllContactGroups)).not.toBeChecked();
    // The host group dataset can be refined, not the services one.
    const addFilterButtons = screen.getAllByLabelText(labelAddFilter);
    expect(addFilterButtons).toHaveLength(2);
    expect(addFilterButtons[0]).toBeEnabled();
    expect(addFilterButtons[1]).toBeDisabled();
    expect(screen.getByLabelText(labelAddNewDataset)).toBeEnabled();
  });

  it('ensures that the form handles an empty name field correctly by showing an error message and disabling the Save button as a validation measure', async () => {
    initialize();

    await userEvent.clear(await screen.findByLabelText(labelName));
    await userEvent.click(screen.getByText(labelRuleProperies));

    expect(await screen.findByText(labelRequired)).toBeVisible();

    expect(screen.getByLabelText(labelSave)).toBeDisabled();
  });

  it('ensures that the form handles an existing name field correctly by showing an error message and disabling the Save button as a validation measure', async () => {
    initialize();

    await screen.findByLabelText(labelName);
    await replaceName('Rule 2');
    await userEvent.click(screen.getByText(labelRuleProperies));

    expect(await screen.findByText(labelNameAlreadyExists)).toBeVisible();

    expect(screen.getByLabelText(labelSave)).toBeDisabled();
  });

  it("ensures that the Save's button initial state is set to disabled", async () => {
    initialize();

    expect(await screen.findByLabelText(labelSave)).toBeDisabled();

    expect(screen.getByLabelText(labelName)).toHaveValue('rule1');
    expect(screen.getByLabelText(labelDescription)).toHaveValue(
      'resourceAccessRule1'
    );
    expect(
      screen.getByLabelText(labelActiveOrInactive).querySelector('input')
    ).toBeChecked();
  });

  it('confirms that the Save button becomes enabled when a modification occurs and the form is error-free', async () => {
    initialize();

    expect(await screen.findByLabelText(labelSave)).toBeDisabled();
    const descriptionInput = screen.getByLabelText(labelDescription);
    await userEvent.clear(descriptionInput);
    await userEvent.type(descriptionInput, 'Rule 1 description');

    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).not.toBeDisabled();
    });
    expect(descriptionInput).toHaveValue('Rule 1 description');
  });

  it('displays configured resources for the Resource Access Rule', async () => {
    initialize();

    expect(await screen.findByText(labelAddResourceDatasets)).toBeVisible();
    expect(screen.getByText('Host group')).toBeVisible();
    expect(screen.getByText('Linux-Servers')).toBeVisible();
    expect(screen.getByText('Host')).toBeVisible();
    expect(screen.getByText('Centreon-Server')).toBeVisible();

    expect(screen.getByText('Service')).toBeVisible();
    expect(screen.getByText('Disk-/var')).toBeVisible();
    expect(screen.getByText('Disk-/usr')).toBeVisible();
    expect(screen.getByText('Disk-/opt')).toBeVisible();
    expect(screen.getByText('Disk-/')).toBeVisible();

    expect(getResourceTypes()).toEqual(['hostgroup', 'host', 'service']);
    expect(screen.getByLabelText(labelName)).toHaveValue('rule1');
    expect(screen.getByLabelText(labelDescription)).toHaveValue(
      'resourceAccessRule1'
    );
    expect(screen.getByLabelText(labelAllHostGroups)).not.toBeChecked();
    // Two datasets, each can be removed.
    expect(screen.getAllByTestId('DeleteOutlineIcon')).toHaveLength(2);
  });

  it('displays configured contacts and contact groups for the Resource Access Rule', async () => {
    initialize();

    expect(await screen.findByText('admin admin')).toBeVisible();
    expect(screen.getByText('centreon-gorgone')).toBeVisible();
    expect(screen.getByText('Guest')).toBeVisible();
    expect(screen.getByText('Supervisor')).toBeVisible();

    // Each chip is in its own field.
    const getField = (testId: string): HTMLElement =>
      screen
        .getByTestId(testId)
        .closest('.MuiAutocomplete-root') as HTMLElement;
    expect(
      within(getField(labelContacts)).getByText('admin admin')
    ).toBeVisible();
    expect(
      within(getField(labelContacts)).getByText('centreon-gorgone')
    ).toBeVisible();
    expect(
      within(getField(labelContactGroups)).getByText('Guest')
    ).toBeVisible();
    expect(
      within(getField(labelContactGroups)).getByText('Supervisor')
    ).toBeVisible();
  });

  it('sends a request to edit a Resource Access Rule when a configured value is changed and the Save button is clicked', async () => {
    initialize();

    await deleteLastDataset();

    await clickSave();

    const { request } = await waitForRequest('@editResourceAccessRuleRequest');
    // Only the first dataset remains: the host group, then the host.
    expect(
      (request.body as { dataset_filters: Array<unknown> }).dataset_filters
    ).toEqual([
      {
        dataset_filter: { dataset_filter: null, resources: [14], type: 'host' },
        resources: [53],
        type: 'hostgroup'
      }
    ]);

    await expectSaved();
  });

  it('sends a request to edit a Resource Access Rule when a configured resources are changed to All resources in datasets', async () => {
    initialize();

    await deleteLastDataset();

    await deleteLastFilter();
    await selectLastResourceType('All resources');
    expect(
      await screen.findByLabelText(labelAllResourcesSelected)
    ).toBeVisible();
    const resourceInputs = getResourceInputs();
    expect(resourceInputs.length).toBeGreaterThan(0);
    resourceInputs.forEach((input) => {
      expect(input).toBeDisabled();
    });

    expect(screen.getByLabelText(labelAddFilter)).toBeDisabled();
    expect(screen.getByLabelText(labelAddNewDataset)).toBeDisabled();
    await clickSave();

    const { request } = await waitForRequest('@editResourceAccessRuleRequest');
    // A single dataset, of all resources.
    expect(
      (request.body as { dataset_filters: Array<unknown> }).dataset_filters
    ).toEqual([{ dataset_filter: null, resources: [], type: 'all' }]);

    await expectSaved();
  });

  it('sends a request to edit a Resource Access Rule when a configured resources are changed to All host groups in datasets', async () => {
    initialize();

    await deleteLastDataset();

    await deleteLastFilter();

    await replaceName('rule#1');

    await userEvent.click(screen.getByLabelText(labelAllHostGroups));
    expect(
      await screen.findByLabelText(labelAllHostGroupsSelected)
    ).toBeVisible();
    expect(screen.getByLabelText(labelAllHostGroupsSelected)).toBeDisabled();
    expect(getChips()).not.toContain('Linux-Servers');

    await clickSave();

    const { request } = await waitForRequest('@editResourceAccessRuleRequest');
    expect(request.body).toEqual(editedRuleFormData);

    await expectSaved();
  });

  it('send a request to edit a Resource Access Rule when business views are added to configuration', async () => {
    initialize({ withBusinessViews: true });

    await deleteLastDataset();

    await deleteLastFilter();

    await replaceName('rule#1');
    await selectLastResourceType(labelBusinessView);

    await openLastResources();
    await waitForRequest('@findBusinessViewsEndpoint');
    await userEvent.click(await screen.findByText('BV1'));
    await openLastResources();
    await userEvent.click(await screen.findByText('BV2'));

    await clickSave();

    const { request } = await waitForRequest('@editResourceAccessRuleRequest');
    expect(request.body).toEqual(editedRuleFormDataiWithBusinessViews);

    await expectSaved();
  });

  it('send a request to edit a Resource Access Rule when all business views are added to configuration', async () => {
    initialize({ withBusinessViews: true });

    await deleteLastDataset();

    await deleteLastFilter();

    await replaceName('rule#1');
    await selectLastResourceType(labelBusinessView);
    await userEvent.click(await screen.findByText(labelAllBusinessViews));
    await clickSave();

    const { request } = await waitForRequest('@editResourceAccessRuleRequest');
    expect(request.body).toEqual(editedRuleFormDataiWithAllBusinessViews);

    await expectSaved();
  });

  it('sends a request to edit a Resource Access Rule when configured contacts and contact groups are changed to all', async () => {
    initialize();

    await deleteLastDataset();

    await deleteLastFilter();

    await replaceName('rule#1');

    await userEvent.click(screen.getByLabelText(labelAllHostGroups));
    expect(
      await screen.findByLabelText(labelAllHostGroupsSelected)
    ).toBeVisible();
    expect(screen.getByLabelText(labelAllHostGroupsSelected)).toBeDisabled();

    await userEvent.click(screen.getByLabelText(labelAllContacts));
    await userEvent.click(screen.getByLabelText(labelAllContactGroups));
    expect(screen.getByLabelText(labelAllContacts)).toBeChecked();
    expect(screen.getByLabelText(labelAllContactGroups)).toBeChecked();

    await clickSave();

    const { request } = await waitForRequest('@editResourceAccessRuleRequest');
    expect(request.body).toEqual(
      editedRuleFormDataWithAllContactsAndContactGroups
    );

    await expectSaved();
  });

  it('displays a confirmation dialog when the form is edited and the Exit button is clicked', async () => {
    initialize();

    await deleteLastDataset();

    await deleteLastFilter();
    await userEvent.click(screen.getByLabelText(labelExit));

    await expectConfirmationDialog();

    await userEvent.click(screen.getByText('Cancel'));
  });

  it('displays a confirmation dialog when the form is edited and the Close button is clicked', async () => {
    initialize();

    await deleteLastDataset();

    await deleteLastFilter();
    await userEvent.click(screen.getByLabelText('close'));

    await expectConfirmationDialog();

    await userEvent.click(screen.getByText('Cancel'));
  });

  it('displays a confiramtion dialog when the form is edited and a click occurs outside the modal', async () => {
    initialize();

    await deleteLastDataset();

    await deleteLastFilter();
    await clickOutsideModal();

    await expectConfirmationDialog();
  });
});
