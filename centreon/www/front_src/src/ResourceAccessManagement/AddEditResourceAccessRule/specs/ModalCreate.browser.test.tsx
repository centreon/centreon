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

import { modalStateAtom } from '../../atom';
import { ModalMode } from '../../models';
import {
  labelActiveOrInactive,
  labelAddFilter,
  labelAddNewDataset,
  labelAddResourceDatasets,
  labelAllBusinessViews,
  labelAllBusinessViewsSelected,
  labelAllContactGroups,
  labelAllContacts,
  labelAllHostGroups,
  labelAllHostGroupsSelected,
  labelAllHosts,
  labelAllResourcesSelected,
  labelBusinessView,
  labelContactGroups,
  labelContacts,
  labelContactsAndContactGroups,
  labelCreateResourceAccessRule,
  labelDelete,
  labelDescription,
  labelDoYouWantToQuitWithoutSaving,
  labelExit,
  labelName,
  labelResourceAccessRuleAddedSuccess,
  labelRuleProperies,
  labelSave,
  labelSelectResource,
  labelSelectResourceType,
  labelYourFormHasUnsavedChanges
} from '../../translatedLabels';
import { AddEditResourceAccessRuleModal } from '..';
import {
  findBusinessViewsEndpoint,
  findContactGroupsEndpoint,
  findContactsEndpoint,
  findHostCategoriesEndpoint,
  findHostGroupsEndpoint,
  findHostsEndpoint,
  findMetaServicesEndpoint,
  findServiceCategoriesEndpoint,
  findServiceGroupsEndpoint,
  findServicesEndpoint,
  resourceAccessRuleEndpoint
} from '../api/endpoints';
import {
  allResourcesFormData,
  findBusinessViewsResponse,
  findContactGroupsResponse,
  findContactsResponse,
  findHostCategoriesResponse,
  findHostGroupsResponse,
  findHostsResponse,
  findMetaServicesResponse,
  findServiceCategoriesResponse,
  findServiceGroupsResponse,
  findServicesResponse,
  formData,
  formDataWithAllBusinessViews,
  formDataWithAllContactGroups,
  formDataWithAllContacts,
  formDataWithAllHostGroups,
  formDataWithBusinessViews,
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
  store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Create });

  if (withBusinessViews) {
    store.set(platformVersionsAtom, platformVersions as PlatformVersions);
  }

  interceptAPIRequest({
    alias: 'addResourceAccessRuleRequest',
    method: Method.POST,
    path: resourceAccessRuleEndpoint({}),
    response: { status: 'ok' }
  });

  interceptAPIRequest({
    alias: 'findHostGroupsEndpoint',
    method: Method.GET,
    path: `${findHostGroupsEndpoint}**`,
    response: findHostGroupsResponse
  });

  interceptAPIRequest({
    alias: 'findHostCategoriesEndpoint',
    method: Method.GET,
    path: `${findHostCategoriesEndpoint}**`,
    response: findHostCategoriesResponse
  });

  interceptAPIRequest({
    alias: 'findHostsEndpoint',
    method: Method.GET,
    path: `${findHostsEndpoint}?*`,
    response: findHostsResponse
  });

  interceptAPIRequest({
    alias: 'findServiceGroupsEndpoint',
    method: Method.GET,
    path: `${findServiceGroupsEndpoint}**`,
    response: findServiceGroupsResponse
  });

  interceptAPIRequest({
    alias: 'findServiceCategoriesEndpoint',
    method: Method.GET,
    path: `${findServiceCategoriesEndpoint}**`,
    response: findServiceCategoriesResponse
  });

  interceptAPIRequest({
    alias: 'findServicesEndpoint',
    method: Method.GET,
    path: `${findServicesEndpoint}?*`,
    response: findServicesResponse
  });

  interceptAPIRequest({
    alias: 'findMetaServicesEndpoint',
    method: Method.GET,
    path: `${findMetaServicesEndpoint}?*`,
    response: findMetaServicesResponse
  });

  interceptAPIRequest({
    alias: 'findContactsEndpoint',
    method: Method.GET,
    path: `${findContactsEndpoint}**`,
    response: findContactsResponse
  });

  interceptAPIRequest({
    alias: 'findContactGroupsEndpoint',
    method: Method.GET,
    path: `${findContactGroupsEndpoint}**`,
    response: findContactGroupsResponse
  });

  interceptAPIRequest({
    alias: 'findBusinessViewsEndpoint',
    method: Method.GET,
    path: `${findBusinessViewsEndpoint}**`,
    response: findBusinessViewsResponse
  });

  // A client of its own, so no cache leaks from one test to the next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

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

// `@centreon/ui` autocompletes put the same test id on the wrapper and the input.
const getResourceInputs = (): Array<HTMLElement> =>
  screen
    .getAllByTestId(labelSelectResource)
    .filter((element) => element.tagName === 'INPUT');

// The label is on the select's wrapper: the menu opens from the combobox in
// it, and the selected type is the value of its hidden input.
const openResourceTypeSelect = async (select: HTMLElement): Promise<void> => {
  await userEvent.click(
    select.querySelector('[role="combobox"]') as HTMLElement
  );
};

const getResourceTypeInput = (select: HTMLElement): HTMLInputElement =>
  select.querySelector('input') as HTMLInputElement;

const selectLastResourceType = async (type: string): Promise<void> => {
  await openResourceTypeSelect(
    getLast(await screen.findAllByLabelText(labelSelectResourceType))
  );
  await userEvent.click(await screen.findByRole('option', { name: type }));
};

const openLastResources = async (): Promise<void> => {
  await userEvent.click(
    getLast(await screen.findAllByTestId(labelSelectResource))
  );
};

const selectOption = async (name: string): Promise<void> => {
  await userEvent.click(await screen.findByText(name));
};

const selectContact = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId(labelContacts));
  await waitForRequest('@findContactsEndpoint');
  await selectOption('centreon-gorgone');
  await userEvent.click(screen.getByTestId(labelContacts));
};

const selectContactGroup = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId(labelContactGroups));
  await waitForRequest('@findContactGroupsEndpoint');
  await selectOption('Supervisors');
  await userEvent.click(screen.getByTestId(labelContactGroups));
};

const fillFormRequiredFields = async (): Promise<void> => {
  await userEvent.type(await screen.findByLabelText(labelName), 'rule#1');
  await userEvent.type(
    screen.getByLabelText(labelDescription),
    'rule#1: Lorem ipsum...'
  );
  await selectLastResourceType('Host group');
  await openLastResources();
  await waitForRequest('@findHostGroupsEndpoint');
  await selectOption('Linux-Servers');

  await userEvent.click(screen.getByLabelText(labelAddFilter));

  await selectLastResourceType('Host');
  await openLastResources();
  await waitForRequest('@findHostsEndpoint');
  await selectOption('Centreon-Server');

  await userEvent.click(screen.getByLabelText(labelAddNewDataset));

  await selectLastResourceType('Service category');
  await openLastResources();
  await waitForRequest('@findServiceCategoriesEndpoint');
  await selectOption('Ping');

  await selectContact();
  await selectContactGroup();
};

// The selected resources, contacts and contact groups are shown as chips.
const expectChips = async (names: Array<string>): Promise<void> => {
  await waitFor(() => {
    const chipLabels = Array.from(
      document.querySelectorAll('.MuiChip-label'),
      (chip) => chip.textContent
    );

    names.forEach((name) => {
      expect(chipLabels).toContain(name);
    });
  });
};

// The `All …` checkboxes offered for a resource type (contacts excluded).
const getAllOfResourceTypeCheckboxes = (): Array<HTMLElement> =>
  screen
    .queryAllByRole('checkbox', { name: /^All / })
    .filter(
      (checkbox) =>
        !(
          [labelAllContacts, labelAllContactGroups] as Array<string | null>
        ).includes(checkbox.closest('label')?.textContent ?? null)
    );

// A click on the backdrop, around the modal.
const clickOutsideModal = async (): Promise<void> => {
  await userEvent.click(
    document.querySelector('.MuiDialog-container') as HTMLElement
  );
};

const expectModalClosed = async (): Promise<void> => {
  await waitFor(() => {
    expect(
      screen.queryByText(labelCreateResourceAccessRule)
    ).not.toBeInTheDocument();
  });
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
  expect(screen.getByText(labelCreateResourceAccessRule)).toBeInTheDocument();
};

describe('Create modal', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays the form', async () => {
    initialize();

    expect(await screen.findByLabelText(labelSave)).toBeVisible();

    expect(screen.getByText(labelCreateResourceAccessRule)).toBeVisible();
    expect(screen.getByText(labelRuleProperies)).toBeVisible();
    expect(screen.getByText(labelAddResourceDatasets)).toBeVisible();
    expect(screen.getByText(labelContactsAndContactGroups)).toBeVisible();

    expect(screen.getByLabelText(labelSave)).toBeDisabled();
    expect(screen.getByLabelText(labelName)).toHaveValue('');
    expect(screen.getByLabelText(labelName)).toHaveAttribute('required');

    expect(screen.getByLabelText(labelDescription)).toHaveValue('');
    expect(screen.getByLabelText(labelDescription)).not.toHaveAttribute(
      'required'
    );

    expect(screen.getByLabelText(labelActiveOrInactive)).toBeVisible();
    expect(screen.getByLabelText(labelActiveOrInactive)).not.toBeDisabled();
    expect(
      screen.getByLabelText(labelActiveOrInactive).querySelector('input')
    ).toBeChecked();

    expect(screen.getByLabelText(labelSelectResourceType)).toBeVisible();
    expect(
      getResourceTypeInput(screen.getByLabelText(labelSelectResourceType))
    ).toHaveValue('');

    const [resourceInput] = getResourceInputs();
    expect(resourceInput).toBeVisible();
    expect(resourceInput).toHaveValue('');
    expect(resourceInput).toBeDisabled();

    expect(screen.getByLabelText(labelAddFilter)).toBeVisible();
    expect(screen.getByLabelText(labelAddFilter)).toBeDisabled();

    expect(screen.getByLabelText(labelAddNewDataset)).toBeVisible();
    expect(screen.getByLabelText(labelAddNewDataset)).toBeDisabled();

    expect(screen.getByLabelText(labelContacts)).toHaveValue('');
    expect(screen.getByLabelText(labelAllContacts)).not.toBeChecked();

    expect(screen.getByLabelText(labelContactGroups)).toHaveValue('');
    expect(screen.getByLabelText(labelAllContactGroups)).not.toBeChecked();

    expect(getAllOfResourceTypeCheckboxes()).toHaveLength(0);
    // A single filter cannot be removed.
    expect(screen.getByTestId(labelDelete)).not.toBeVisible();
    expect(screen.queryByTestId('DeleteOutlineIcon')).not.toBeInTheDocument();
  });

  it('confirms that the Save button is activated when the form is filled and error-free', async () => {
    initialize();

    expect(await screen.findByLabelText(labelSave)).toBeDisabled();

    await fillFormRequiredFields();

    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).not.toBeDisabled();
    });

    expect(screen.getByLabelText(labelName)).toHaveValue('rule#1');
    expect(screen.getByLabelText(labelDescription)).toHaveValue(
      'rule#1: Lorem ipsum...'
    );
    await expectChips([
      'Linux-Servers',
      'Centreon-Server',
      'Ping',
      'centreon-gorgone',
      'Supervisors'
    ]);
  });

  it('confirms that the Refine filter and Add new dataset buttons are disabled when resource type and/or resources in dataset are not selected', async () => {
    initialize();

    expect(await screen.findByText(labelAddFilter)).toBeDisabled();
    expect(screen.getByText(labelAddNewDataset)).toBeDisabled();

    await selectLastResourceType('Host');

    await waitFor(() => {
      expect(getResourceInputs()[0]).toBeEnabled();
    });
    expect(screen.getByText(labelAddFilter)).toBeDisabled();
    expect(screen.getByText(labelAddNewDataset)).toBeDisabled();

    await openLastResources();
    await waitForRequest('@findHostsEndpoint');
    await selectOption('Centreon-Server');

    await userEvent.click(screen.getByText(labelAddFilter));

    // A second, empty filter: both filters can now be removed.
    await waitFor(() => {
      expect(screen.getAllByLabelText(labelSelectResourceType)).toHaveLength(2);
    });
    expect(
      getResourceTypeInput(
        getLast(screen.getAllByLabelText(labelSelectResourceType))
      )
    ).toHaveValue('');
    screen.getAllByTestId(labelDelete).forEach((deleteButton) => {
      expect(deleteButton).toBeVisible();
    });
    expect(screen.getAllByTestId(labelDelete)).toHaveLength(2);
    expect(screen.getByLabelText(labelAllHosts)).not.toBeChecked();

    expect(screen.getByText(labelAddFilter)).toBeDisabled();
    expect(screen.getByText(labelAddNewDataset)).toBeDisabled();
  });

  it('confirms that the Refine filter and Add new dataset buttons are enabled when a dataset is selected', async () => {
    initialize();

    expect(await screen.findByText(labelAddFilter)).toBeDisabled();
    expect(screen.getByText(labelAddNewDataset)).toBeDisabled();

    await selectLastResourceType('Host');
    await openLastResources();
    await waitForRequest('@findHostsEndpoint');
    await selectOption('Centreon-Server');

    await waitFor(() => {
      expect(screen.getByText(labelAddFilter)).not.toBeDisabled();
    });
    expect(screen.getByText(labelAddNewDataset)).not.toBeDisabled();

    await expectChips(['Centreon-Server']);
    expect(screen.getByLabelText(labelAllHosts)).not.toBeChecked();
  });

  it('confirms that the Refine filter button is disabled and Add new dataset button is enabled when a dataset for metaservice is setup', async () => {
    initialize();

    expect(await screen.findByText(labelAddFilter)).toBeDisabled();
    expect(screen.getByText(labelAddNewDataset)).toBeDisabled();

    await selectLastResourceType('Metaservice');
    await openLastResources();
    await waitForRequest('@findMetaServicesEndpoint');
    await selectOption('META_SERVICE_MEMORY_PARIS');

    await waitFor(() => {
      expect(screen.getByText(labelAddNewDataset)).not.toBeDisabled();
    });
    expect(screen.getByText(labelAddFilter)).toBeDisabled();

    await expectChips(['META_SERVICE_MEMORY_PARIS']);
    // No "all meta services" choice.
    expect(getAllOfResourceTypeCheckboxes()).toHaveLength(0);
  });

  it('confirms that the Refine filter button is disabled and the Add new dataset button is enabled when a dataset for a service is selected', async () => {
    initialize();

    expect(await screen.findByText(labelAddFilter)).toBeDisabled();
    expect(screen.getByText(labelAddNewDataset)).toBeDisabled();

    await selectLastResourceType('Service');
    await openLastResources();
    await waitForRequest('@findServicesEndpoint');
    await selectOption('Ping');

    await waitFor(() => {
      expect(screen.getByText(labelAddNewDataset)).not.toBeDisabled();
    });
    expect(screen.getByText(labelAddFilter)).toBeDisabled();

    await expectChips(['Ping']);
    // No "all services" choice.
    expect(getAllOfResourceTypeCheckboxes()).toHaveLength(0);
  });

  it('confirms that the Delete dataset filters button is visible when at least two dataset filters are selected', async () => {
    initialize();

    await fillFormRequiredFields();

    const deleteDatasetButtons =
      await screen.findAllByTestId('DeleteOutlineIcon');
    // One per dataset.
    expect(deleteDatasetButtons).toHaveLength(2);
    deleteDatasetButtons.forEach((button) => {
      expect(button).toBeVisible();
    });
  });

  it('confirms that on click of Delete dataset filters button when two dataset filters are selected, the button disappears', async () => {
    initialize();

    await fillFormRequiredFields();

    const deleteDatasetButtons =
      await screen.findAllByTestId('DeleteOutlineIcon');
    expect(deleteDatasetButtons).toHaveLength(2);
    deleteDatasetButtons.forEach((button) => {
      expect(button).toBeVisible();
    });

    await userEvent.click(deleteDatasetButtons[0]);

    // The first dataset (host group and host filters) is gone, the service
    // category one is left.
    await waitFor(() => {
      expect(screen.getAllByLabelText(labelSelectResourceType)).toHaveLength(1);
    });
    expect(
      getResourceTypeInput(screen.getByLabelText(labelSelectResourceType))
    ).toHaveValue('service_category');
    await expectChips(['Ping']);
    expect(screen.queryByText('Linux-Servers')).not.toBeInTheDocument();
    expect(screen.queryByText('Centreon-Server')).not.toBeInTheDocument();

    expect(screen.queryAllByTestId('DeleteOutlineIcon')).toHaveLength(0);
  });

  it('confirms that the Delete dataset icon is visible when at least two datasets are selected', async () => {
    initialize();

    await fillFormRequiredFields();

    // The first dataset has two filters, each with its delete button.
    const deleteButtons = await screen.findAllByTestId(labelDelete);
    const visibleDeleteButtons = deleteButtons.filter((button) =>
      button.checkVisibility({ visibilityProperty: true })
    );
    expect(visibleDeleteButtons).toHaveLength(2);
    visibleDeleteButtons.forEach((button) => {
      expect(button).toBeVisible();
    });
  });

  it('confirms that on click of Delete dataset icon when two datasets are selected, the icon disappears', async () => {
    initialize();

    await fillFormRequiredFields();

    const deleteButtons = await screen.findAllByTestId(labelDelete);
    expect(deleteButtons[0]).toBeVisible();

    await userEvent.click(deleteButtons[0]);

    // The host group filter is gone: each dataset has a single filter left,
    // which cannot be removed.
    await waitFor(() => {
      expect(screen.getAllByLabelText(labelSelectResourceType)).toHaveLength(2);
    });
    expect(
      screen
        .getAllByLabelText(labelSelectResourceType)
        .map((select) => getResourceTypeInput(select).value)
    ).toEqual(['host', 'service_category']);
    expect(screen.queryByText('Linux-Servers')).not.toBeInTheDocument();

    const remainingDeleteButtons = screen.getAllByTestId(labelDelete);
    expect(remainingDeleteButtons).toHaveLength(2);
    remainingDeleteButtons.forEach((button) => {
      expect(button).not.toBeVisible();
    });
    // Both datasets can still be removed.
    expect(screen.getAllByTestId('DeleteOutlineIcon')).toHaveLength(2);
  });

  it('sends a request to add a new Resource Access Rule with the form values when the Save button is clicked', async () => {
    initialize();

    expect(await screen.findByLabelText(labelSave)).toBeDisabled();

    await fillFormRequiredFields();

    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).toBeEnabled();
    });
    await userEvent.click(screen.getByLabelText(labelSave));

    const { request } = await waitForRequest('@addResourceAccessRuleRequest');
    expect(request.body).toEqual(formData);

    expect(
      await screen.findByText(labelResourceAccessRuleAddedSuccess)
    ).toBeVisible();
    await expectModalClosed();
  });

  it('sends a request to add a new Resource Access Rule when All resources are selected', async () => {
    initialize();

    await userEvent.type(await screen.findByLabelText(labelName), 'rule#0');
    await userEvent.type(
      screen.getByLabelText(labelDescription),
      'rule#0: Lorem ipsum...'
    );
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

    await selectContact();
    await selectContactGroup();

    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).toBeEnabled();
    });
    await userEvent.click(screen.getByLabelText(labelSave));
    const { request } = await waitForRequest('@addResourceAccessRuleRequest');
    expect(request.body).toEqual(allResourcesFormData);

    expect(
      await screen.findByText(labelResourceAccessRuleAddedSuccess)
    ).toBeVisible();
    await expectModalClosed();
  });

  it('sends a request to add a new Resource Access Rule when all host groups are selected', async () => {
    initialize();

    expect(await screen.findByLabelText(labelSave)).toBeDisabled();

    await userEvent.type(screen.getByLabelText(labelName), 'rule#1');
    await userEvent.type(
      screen.getByLabelText(labelDescription),
      'rule#1: Lorem ipsum...'
    );
    await selectLastResourceType('Host group');
    await openLastResources();
    await waitForRequest('@findHostGroupsEndpoint');
    await selectOption('Linux-Servers');

    await selectContact();
    await selectContactGroup();

    await userEvent.click(screen.getByLabelText(labelAllHostGroups));
    expect(
      await screen.findByLabelText(labelAllHostGroupsSelected)
    ).toBeVisible();
    expect(screen.getByLabelText(labelAllHostGroupsSelected)).toBeDisabled();
    expect(screen.getByLabelText(labelAllHostGroups)).toBeChecked();
    // The host groups picked before are cleared.
    expect(screen.queryByText('Linux-Servers')).not.toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).toBeEnabled();
    });
    await userEvent.click(screen.getByLabelText(labelSave));

    const { request } = await waitForRequest('@addResourceAccessRuleRequest');
    expect(request.body).toEqual(formDataWithAllHostGroups);

    expect(
      await screen.findByText(labelResourceAccessRuleAddedSuccess)
    ).toBeVisible();
    await expectModalClosed();
  });

  it('displays the business view resource type when the BAM module is installed', async () => {
    initialize({ withBusinessViews: true });

    await openResourceTypeSelect(
      await screen.findByLabelText(labelSelectResourceType)
    );

    const businessViewOption = await screen.findByText(labelBusinessView);
    expect(businessViewOption).toBeVisible();
    await userEvent.click(businessViewOption);

    expect(await screen.findByText(labelAllBusinessViews)).toBeVisible();
    expect(screen.getByLabelText(labelAllBusinessViews)).not.toBeChecked();
    expect(screen.getByText(labelAddFilter)).toBeDisabled();
    expect(screen.getByText(labelAddNewDataset)).toBeDisabled();
    expect(getResourceInputs()[0]).toBeEnabled();
  });

  it('sends a request to create a new Resource Access Rule when business views are selected', async () => {
    initialize({ withBusinessViews: true });

    await userEvent.type(await screen.findByLabelText(labelName), 'rule#1');
    await userEvent.type(
      screen.getByLabelText(labelDescription),
      'rule#1: Lorem ipsum...'
    );

    await selectLastResourceType(labelBusinessView);

    await openLastResources();
    await waitForRequest('@findBusinessViewsEndpoint');
    await selectOption('BV1');
    await openLastResources();
    await selectOption('BV2');

    await selectContact();
    await selectContactGroup();

    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).toBeEnabled();
    });
    await userEvent.click(screen.getByLabelText(labelSave));
    const { request } = await waitForRequest('@addResourceAccessRuleRequest');
    expect(request.body).toEqual(formDataWithBusinessViews);

    expect(
      await screen.findByText(labelResourceAccessRuleAddedSuccess)
    ).toBeVisible();
    await expectModalClosed();
  });

  it('sends a request to add a new Resource Access Rule when all contacts are selected', async () => {
    initialize();

    expect(await screen.findByLabelText(labelSave)).toBeDisabled();

    await userEvent.type(screen.getByLabelText(labelName), 'rule#1');
    await userEvent.type(
      screen.getByLabelText(labelDescription),
      'rule#1: Lorem ipsum...'
    );
    await selectLastResourceType('Host group');
    await openLastResources();
    await waitForRequest('@findHostGroupsEndpoint');
    await selectOption('Linux-Servers');

    await userEvent.click(screen.getByLabelText(labelAllHostGroups));
    expect(
      await screen.findByLabelText(labelAllHostGroupsSelected)
    ).toBeVisible();
    expect(screen.getByLabelText(labelAllHostGroupsSelected)).toBeDisabled();

    await userEvent.click(screen.getByLabelText(labelAllContacts));
    expect(screen.getByLabelText(labelAllContacts)).toBeChecked();

    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).toBeEnabled();
    });
    await userEvent.click(screen.getByLabelText(labelSave));

    const { request } = await waitForRequest('@addResourceAccessRuleRequest');
    expect(request.body).toEqual(formDataWithAllContacts);

    expect(
      await screen.findByText(labelResourceAccessRuleAddedSuccess)
    ).toBeVisible();
    await expectModalClosed();
  });

  it('sends a request to create a new Resource Access Rule when all business views are selected', async () => {
    initialize({ withBusinessViews: true });

    await userEvent.type(await screen.findByLabelText(labelName), 'rule#1');
    await userEvent.type(
      screen.getByLabelText(labelDescription),
      'rule#1: Lorem ipsum...'
    );

    await selectLastResourceType(labelBusinessView);
    await userEvent.click(await screen.findByText(labelAllBusinessViews));

    expect(
      await screen.findByLabelText(labelAllBusinessViewsSelected)
    ).toBeVisible();
    expect(screen.getByLabelText(labelAllBusinessViewsSelected)).toBeDisabled();

    await selectContact();
    await selectContactGroup();

    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).toBeEnabled();
    });
    await userEvent.click(screen.getByLabelText(labelSave));
    const { request } = await waitForRequest('@addResourceAccessRuleRequest');
    expect(request.body).toEqual(formDataWithAllBusinessViews);

    expect(
      await screen.findByText(labelResourceAccessRuleAddedSuccess)
    ).toBeVisible();
    await expectModalClosed();
  });

  it('sends a request to add a new Resource Access Rule when all contact groups are selected', async () => {
    initialize();

    expect(await screen.findByLabelText(labelSave)).toBeDisabled();

    await userEvent.type(screen.getByLabelText(labelName), 'rule#1');
    await userEvent.type(
      screen.getByLabelText(labelDescription),
      'rule#1: Lorem ipsum...'
    );
    await selectLastResourceType('Host group');
    await openLastResources();
    await waitForRequest('@findHostGroupsEndpoint');
    await selectOption('Linux-Servers');

    await userEvent.click(screen.getByLabelText(labelAllHostGroups));
    expect(
      await screen.findByLabelText(labelAllHostGroupsSelected)
    ).toBeVisible();
    expect(screen.getByLabelText(labelAllHostGroupsSelected)).toBeDisabled();

    await userEvent.click(screen.getByLabelText(labelAllContactGroups));
    expect(screen.getByLabelText(labelAllContactGroups)).toBeChecked();

    await waitFor(() => {
      expect(screen.getByLabelText(labelSave)).toBeEnabled();
    });
    await userEvent.click(screen.getByLabelText(labelSave));

    const { request } = await waitForRequest('@addResourceAccessRuleRequest');
    expect(request.body).toEqual(formDataWithAllContactGroups);

    expect(
      await screen.findByText(labelResourceAccessRuleAddedSuccess)
    ).toBeVisible();
    await expectModalClosed();
  });

  it('displays a confirmation dialog when the form is filled and the Exit button is clicked', async () => {
    initialize();

    await fillFormRequiredFields();

    await userEvent.click(screen.getByLabelText(labelExit));

    await expectConfirmationDialog();

    await userEvent.click(screen.getByText('Cancel'));
  });

  it('displays a confirmation dialog when the form is filled and the Close button is clicked', async () => {
    initialize();

    await fillFormRequiredFields();

    await userEvent.click(screen.getByLabelText('close'));

    await expectConfirmationDialog();

    await userEvent.click(screen.getByText('Cancel'));
  });

  it('dispalys a confirmation dialog when the form is filled and a click occurs outside the modal', async () => {
    initialize();

    await fillFormRequiredFields();

    await clickOutsideModal();

    await expectConfirmationDialog();
  });
});
