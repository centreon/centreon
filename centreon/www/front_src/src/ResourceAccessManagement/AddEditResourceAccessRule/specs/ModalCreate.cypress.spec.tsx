import { ReactElement } from 'react';

import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { modalStateAtom } from '../../atom';
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
  labelActiveOrInactive,
  labelAddNewDataset,
  labelContactGroups,
  labelContacts,
  labelDescription,
  labelName,
  labelAddFilter,
  labelResourceAccessRuleAddedSuccess,
  labelSave,
  labelSelectResource,
  labelSelectResourceType,
  labelAllResourcesSelected,
  labelAllHostGroups,
  labelAllHostGroupsSelected,
  labelBusinessView,
  labelAllBusinessViews,
  labelAllBusinessViewsSelected,
  labelAllContacts,
  labelAllContactGroups,
  labelExit,
  labelYourFormHasUnsavedChanges,
  labelDoYouWantToQuitWithoutSaving
} from '../../translatedLabels';
import { ModalMode } from '../../models';

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
  formDataWithBusinessViews,
  platformVersions,
  formDataWithAllContactGroups,
  formDataWithAllContacts,
  formDataWithAllHostGroups
} from './testUtils';

import { platformVersionsAtom } from 'www/front_src/src/Main/atoms/platformVersionsAtom';

const store = createStore();
store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Create });

const ModalWithQueryProvider = (): ReactElement => {
  return (
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <TestQueryProvider>
          <SnackbarProvider>
            <AddEditResourceAccessRuleModal />
          </SnackbarProvider>
        </TestQueryProvider>
      </Provider>
    </div>
  );
};

const initialize = (): void => {
  cy.interceptAPIRequest({
    alias: 'addResourceAccessRuleRequest',
    method: Method.POST,
    path: resourceAccessRuleEndpoint({}),
    response: { status: 'ok' }
  });

  cy.interceptAPIRequest({
    alias: 'findHostGroupsEndpoint',
    method: Method.GET,
    path: `${findHostGroupsEndpoint}**`,
    response: findHostGroupsResponse
  });

  cy.interceptAPIRequest({
    alias: 'findHostCategoriesEndpoint',
    method: Method.GET,
    path: `${findHostCategoriesEndpoint}**`,
    response: findHostCategoriesResponse
  });

  cy.interceptAPIRequest({
    alias: 'findHostsEndpoint',
    method: Method.GET,
    path: `${findHostsEndpoint}?*`,
    response: findHostsResponse
  });

  cy.interceptAPIRequest({
    alias: 'findServiceGroupsEndpoint',
    method: Method.GET,
    path: `${findServiceGroupsEndpoint}**`,
    response: findServiceGroupsResponse
  });

  cy.interceptAPIRequest({
    alias: 'findServiceCategoriesEndpoint',
    method: Method.GET,
    path: `${findServiceCategoriesEndpoint}**`,
    response: findServiceCategoriesResponse
  });

  cy.interceptAPIRequest({
    alias: 'findServicesEndpoint',
    method: Method.GET,
    path: `${findServicesEndpoint}?*`,
    response: findServicesResponse
  });

  cy.interceptAPIRequest({
    alias: 'findMetaServicesEndpoint',
    method: Method.GET,
    path: `${findMetaServicesEndpoint}?*`,
    response: findMetaServicesResponse
  });

  cy.interceptAPIRequest({
    alias: 'findContactsEndpoint',
    method: Method.GET,
    path: `${findContactsEndpoint}**`,
    response: findContactsResponse
  });

  cy.interceptAPIRequest({
    alias: 'findContactGroupsEndpoint',
    method: Method.GET,
    path: `${findContactGroupsEndpoint}**`,
    response: findContactGroupsResponse
  });

  cy.interceptAPIRequest({
    alias: 'findBusinessViewsEndpoint',
    method: Method.GET,
    path: `${findBusinessViewsEndpoint}**`,
    response: findBusinessViewsResponse
  });

  cy.viewport('macbook-13');

  cy.mount({
    Component: <ModalWithQueryProvider />
  });
};

const fillFormRequiredFields = (): void => {
  cy.findByLabelText(labelName).type('rule#1');
  cy.findByLabelText(labelDescription).type('rule#1: Lorem ipsum...');
  cy.findAllByLabelText(labelSelectResourceType).last().click();
  cy.findByText('Host group').click();
  cy.findAllByTestId(labelSelectResource).last().click();
  cy.waitForRequest('@findHostGroupsEndpoint');
  cy.findByText('Linux-Servers').click();

  cy.findByLabelText(labelAddFilter).click();

  cy.findAllByLabelText(labelSelectResourceType).last().click();
  cy.findByText('Host').click();
  cy.findAllByTestId(labelSelectResource).last().click();
  cy.waitForRequest('@findHostsEndpoint');
  cy.findByText('Centreon-Server').click();

  cy.findByLabelText(labelAddNewDataset).click();

  cy.findAllByLabelText(labelSelectResourceType).last().click();
  cy.findByText('Service category').click();
  cy.findAllByTestId(labelSelectResource).last().click();
  cy.waitForRequest('@findServiceCategoriesEndpoint');
  cy.findByText('Ping').click();

  cy.findByTestId(labelContacts).click();
  cy.waitForRequest('@findContactsEndpoint');
  cy.findByText('centreon-gorgone').click();
  cy.findByTestId(labelContacts).click();

  cy.findByTestId(labelContactGroups).click();
  cy.waitForRequest('@findContactGroupsEndpoint');
  cy.findByText('Supervisors').click();
  cy.findByTestId(labelContactGroups).click();
};

describe('Create modal', () => {
  beforeEach(initialize);

  it('displays the form', () => {
    cy.findByLabelText(labelSave).should('be.visible');

    cy.findByLabelText(labelSave).should('be.disabled');
    cy.findByLabelText(labelName).should('have.value', '');
    cy.findByLabelText(labelName).should('have.attr', 'required');

    cy.findByLabelText(labelDescription).should('have.value', '');
    cy.findByLabelText(labelDescription).should('not.have.attr', 'required');

    cy.findByLabelText(labelActiveOrInactive).should('be.visible');
    cy.findByLabelText(labelActiveOrInactive).should('not.be.disabled');

    cy.findByLabelText(labelSelectResourceType).should('be.visible');
    cy.findByLabelText(labelSelectResourceType).should('have.value', '');

    cy.findByTestId(labelSelectResource).should('be.visible');
    cy.findByTestId(labelSelectResource).should('have.value', '');

    cy.findByLabelText(labelAddFilter).should('be.visible');
    cy.findByLabelText(labelAddFilter).should('be.disabled');

    cy.findByLabelText(labelAddNewDataset).should('be.visible');
    cy.findByLabelText(labelAddNewDataset).should('be.disabled');

    cy.findByLabelText(labelContacts).should('have.value', '');

    cy.findByLabelText(labelContactGroups).should('have.value', '');

    cy.makeSnapshot();
  });

  it('confirms that the Save button is activated when the form is filled and error-free', () => {
    cy.findByLabelText(labelSave).should('be.disabled');

    fillFormRequiredFields();

    cy.findByLabelText(labelSave).should('not.be.disabled');

    cy.makeSnapshot();
  });

  it('confirms that the Refine filter and Add new dataset buttons are disabled when resource type and/or resources in dataset are not selected', () => {
    cy.findByText(labelAddFilter).should('be.disabled');
    cy.findByText(labelAddNewDataset).should('be.disabled');

    cy.findByLabelText(labelSelectResourceType).click();
    cy.findByText('Host').click();

    cy.findByText(labelAddFilter).should('be.disabled');
    cy.findByText(labelAddNewDataset).should('be.disabled');

    cy.findByTestId(labelSelectResource).click();
    cy.waitForRequest('@findHostsEndpoint');
    cy.findByText('Centreon-Server').click();

    cy.findByText(labelAddFilter).click();

    cy.findByText(labelAddFilter).should('be.disabled');
    cy.findByText(labelAddNewDataset).should('be.disabled');

    cy.makeSnapshot();
  });

  it('confirms that the Refine filter and Add new dataset buttons are enabled when a dataset is selected', () => {
    cy.findByText(labelAddFilter).should('be.disabled');
    cy.findByText(labelAddNewDataset).should('be.disabled');

    cy.findByLabelText(labelSelectResourceType).click();
    cy.findByText('Host').click();
    cy.findByTestId(labelSelectResource).click();
    cy.waitForRequest('@findHostsEndpoint');
    cy.findByText('Centreon-Server').click();

    cy.findByText(labelAddFilter).should('not.be.disabled');
    cy.findByText(labelAddNewDataset).should('not.be.disabled');

    cy.makeSnapshot();
  });

  it('confirms that the Refine filter button is disabled and Add new dataset button is enabled when a dataset for metaservice is setup', () => {
    cy.findByText(labelAddFilter).should('be.disabled');
    cy.findByText(labelAddNewDataset).should('be.disabled');

    cy.findByLabelText(labelSelectResourceType).click();
    cy.findByText('Metaservice').click();
    cy.findByTestId(labelSelectResource).click();
    cy.waitForRequest('@findMetaServicesEndpoint');
    cy.findByText('META_SERVICE_MEMORY_PARIS').click();

    cy.findByText(labelAddFilter).should('be.disabled');
    cy.findByText(labelAddNewDataset).should('not.be.disabled');

    cy.makeSnapshot();
  });

  it('confirms that the Refine filter button is disabled and the Add new dataset button is enabled when a dataset for a service is selected', () => {
    cy.findByText(labelAddFilter).should('be.disabled');
    cy.findByText(labelAddNewDataset).should('be.disabled');

    cy.findByLabelText(labelSelectResourceType).click();
    cy.findByText('Service').click();
    cy.findByTestId(labelSelectResource).click();
    cy.waitForRequest('@findServicesEndpoint');
    cy.findByText('Ping').click();

    cy.findByText(labelAddFilter).should('be.disabled');
    cy.findByText(labelAddNewDataset).should('not.be.disabled');

    cy.makeSnapshot();
  });

  it('confirms that the Delete dataset filters button is visible when at least two dataset filters are selected', () => {
    fillFormRequiredFields();

    cy.findAllByTestId('DeleteOutlineIcon').should('be.visible');

    cy.makeSnapshot();
  });

  it('confirms that on click of Delete dataset filters button when two dataset filters are selected, the button disappears', () => {
    fillFormRequiredFields();

    cy.findAllByTestId('DeleteOutlineIcon').should('be.visible');

    cy.findAllByTestId('DeleteOutlineIcon').first().click();

    cy.findAllByTestId('DeleteOutlineIcon').should('not.exist');

    cy.makeSnapshot();
  });

  it('confirms that the Delete dataset icon is visible when at least two datasets are selected', () => {
    fillFormRequiredFields();

    cy.findAllByTestId('Delete').should('be.visible');

    cy.makeSnapshot();
  });

  it('confirms that on click of Delete dataset icon when two datasets are selected, the icon disappears', () => {
    fillFormRequiredFields();

    cy.findAllByTestId('Delete').should('be.visible');

    cy.findAllByTestId('Delete').first().click();

    cy.findAllByTestId('Delete').should('not.be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to add a new Resource Access Rule with the form values when the Save button is clicked', () => {
    cy.findByLabelText(labelSave).should('be.disabled');

    fillFormRequiredFields();

    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@addResourceAccessRuleRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(formData);
    });

    cy.findByText(labelResourceAccessRuleAddedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to add a new Resource Access Rule when All resources are selected', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Create });

    cy.findByLabelText(labelName).type('rule#0');
    cy.findByLabelText(labelDescription).type('rule#0: Lorem ipsum...');
    cy.findAllByLabelText(labelSelectResourceType).last().click();
    cy.findByText('All resources').click();
    cy.findByLabelText(labelAllResourcesSelected).should('be.visible');
    cy.findAllByTestId(labelSelectResource).should('be.disabled');

    cy.findByLabelText(labelAddFilter).should('be.disabled');
    cy.findByLabelText(labelAddNewDataset).should('be.disabled');

    cy.findByTestId(labelContacts).click();
    cy.waitForRequest('@findContactsEndpoint');
    cy.findByText('centreon-gorgone').click();
    cy.findByTestId(labelContacts).click();

    cy.findByTestId(labelContactGroups).click();
    cy.waitForRequest('@findContactGroupsEndpoint');
    cy.findByText('Supervisors').click();
    cy.findByTestId(labelContactGroups).click();

    cy.findByLabelText(labelSave).click();
    cy.waitForRequest('@addResourceAccessRuleRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(allResourcesFormData);
    });

    cy.findByText(labelResourceAccessRuleAddedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to add a new Resource Access Rule when all host groups are selected', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Create });
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.findByLabelText(labelName).type('rule#1');
    cy.findByLabelText(labelDescription).type('rule#1: Lorem ipsum...');
    cy.findAllByLabelText(labelSelectResourceType).last().click();
    cy.findByText('Host group').click();
    cy.findAllByTestId(labelSelectResource).last().click();
    cy.waitForRequest('@findHostGroupsEndpoint');
    cy.findByText('Linux-Servers').click();

    cy.findByTestId(labelContacts).click();
    cy.waitForRequest('@findContactsEndpoint');
    cy.findByText('centreon-gorgone').click();
    cy.findByTestId(labelContacts).click();

    cy.findByTestId(labelContactGroups).click();
    cy.waitForRequest('@findContactGroupsEndpoint');
    cy.findByText('Supervisors').click();
    cy.findByTestId(labelContactGroups).click();

    cy.findByLabelText(labelAllHostGroups).click();
    cy.findByLabelText(labelAllHostGroupsSelected).should('be.visible');
    cy.findByLabelText(labelAllHostGroupsSelected).should('be.disabled');

    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@addResourceAccessRuleRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(formDataWithAllHostGroups);
    });

    cy.findByText(labelResourceAccessRuleAddedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the business view resource type when the BAM module is installed', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Create });
    store.set(platformVersionsAtom, platformVersions);
    cy.findByLabelText(labelSelectResourceType).click();

    cy.findByText(labelBusinessView).should('be.visible').click();
    cy.findByText(labelAllBusinessViews).should('be.visible');
    cy.findByText(labelAddFilter).should('be.disabled');

    cy.makeSnapshot();
  });

  it('sends a request to create a new Resource Access Rule when business views are selected', () => {
    cy.findByLabelText(labelName).type('rule#1');
    cy.findByLabelText(labelDescription).type('rule#1: Lorem ipsum...');

    cy.findAllByLabelText(labelSelectResourceType).last().click();
    cy.findByText(labelBusinessView).click();

    cy.findAllByTestId(labelSelectResource).last().click();
    cy.waitForRequest('@findBusinessViewsEndpoint');
    cy.findByText('BV1').click();
    cy.findAllByTestId(labelSelectResource).last().click();
    cy.findByText('BV2').click();

    cy.findByTestId(labelContacts).click();
    cy.waitForRequest('@findContactsEndpoint');
    cy.findByText('centreon-gorgone').click();
    cy.findByTestId(labelContacts).click();

    cy.findByTestId(labelContactGroups).click();
    cy.waitForRequest('@findContactGroupsEndpoint');
    cy.findByText('Supervisors').click();
    cy.findByTestId(labelContactGroups).click();

    cy.findByLabelText(labelSave).click();
    cy.waitForRequest('@addResourceAccessRuleRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(formDataWithBusinessViews);
    });

    cy.findByText(labelResourceAccessRuleAddedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to add a new Resource Access Rule when all contacts are selected', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Create });
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.findByLabelText(labelName).type('rule#1');
    cy.findByLabelText(labelDescription).type('rule#1: Lorem ipsum...');
    cy.findAllByLabelText(labelSelectResourceType).last().click();
    cy.findByText('Host group').click();
    cy.findAllByTestId(labelSelectResource).last().click();
    cy.waitForRequest('@findHostGroupsEndpoint');
    cy.findByText('Linux-Servers').click();

    cy.findByLabelText(labelAllHostGroups).click();
    cy.findByLabelText(labelAllHostGroupsSelected).should('be.visible');
    cy.findByLabelText(labelAllHostGroupsSelected).should('be.disabled');

    cy.findByLabelText(labelAllContacts).click();

    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@addResourceAccessRuleRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(formDataWithAllContacts);
    });

    cy.findByText(labelResourceAccessRuleAddedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to create a new Resource Access Rule when all business views are selected', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Create });
    cy.findByLabelText(labelName).type('rule#1');
    cy.findByLabelText(labelDescription).type('rule#1: Lorem ipsum...');

    cy.findAllByLabelText(labelSelectResourceType).last().click();
    cy.findByText(labelBusinessView).click();
    cy.findByText(labelAllBusinessViews).click();

    cy.findByLabelText(labelAllBusinessViewsSelected).should('be.visible');
    cy.findByLabelText(labelAllBusinessViewsSelected).should('be.disabled');

    cy.findByTestId(labelContacts).click();
    cy.waitForRequest('@findContactsEndpoint');
    cy.findByText('centreon-gorgone').click();
    cy.findByTestId(labelContacts).click();

    cy.findByTestId(labelContactGroups).click();
    cy.waitForRequest('@findContactGroupsEndpoint');
    cy.findByText('Supervisors').click();
    cy.findByTestId(labelContactGroups).click();

    cy.findByLabelText(labelSave).click();
    cy.waitForRequest('@addResourceAccessRuleRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(
        formDataWithAllBusinessViews
      );
    });

    cy.findByText(labelResourceAccessRuleAddedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to add a new Resource Access Rule when all contact groups are selected', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Create });
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.findByLabelText(labelName).type('rule#1');
    cy.findByLabelText(labelDescription).type('rule#1: Lorem ipsum...');
    cy.findAllByLabelText(labelSelectResourceType).last().click();
    cy.findByText('Host group').click();
    cy.findAllByTestId(labelSelectResource).last().click();
    cy.waitForRequest('@findHostGroupsEndpoint');
    cy.findByText('Linux-Servers').click();

    cy.findByLabelText(labelAllHostGroups).click();
    cy.findByLabelText(labelAllHostGroupsSelected).should('be.visible');
    cy.findByLabelText(labelAllHostGroupsSelected).should('be.disabled');

    cy.findByLabelText(labelAllContactGroups).click();

    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@addResourceAccessRuleRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(
        formDataWithAllContactGroups
      );
    });

    cy.findByText(labelResourceAccessRuleAddedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a confirmation dialog when the form is filled and the Exit button is clicked', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Create });
    fillFormRequiredFields();

    cy.findByLabelText(labelExit).click();

    cy.findByText(labelYourFormHasUnsavedChanges).should('be.visible');
    cy.findByText(labelDoYouWantToQuitWithoutSaving).should('be.visible');

    cy.makeSnapshot();

    cy.findByText('Cancel').click();
  });

  it('displays a confirmation dialog when the form is filled and the Close button is clicked', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Create });
    fillFormRequiredFields();

    cy.findByLabelText('close').click();

    cy.findByText(labelYourFormHasUnsavedChanges).should('be.visible');
    cy.findByText(labelDoYouWantToQuitWithoutSaving).should('be.visible');

    cy.makeSnapshot();

    cy.findByText('Cancel').click();
  });

  it('dispalys a confirmation dialog when the form is filled and a click occurs outside the modal', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Create });
    fillFormRequiredFields();

    cy.clickOutside();

    cy.findByText(labelYourFormHasUnsavedChanges).should('be.visible');
    cy.findByText(labelDoYouWantToQuitWithoutSaving).should('be.visible');

    cy.makeSnapshot();
  });
});
