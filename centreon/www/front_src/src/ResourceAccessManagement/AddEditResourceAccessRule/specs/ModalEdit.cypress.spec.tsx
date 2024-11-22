import { ReactElement } from 'react';

import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import { AddEditResourceAccessRuleModal } from '..';
import {
  editedResourceAccessRuleIdAtom,
  modalStateAtom,
  resourceAccessRulesNamesAtom
} from '../../atom';
import { ModalMode } from '../../models';
import {
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
  labelContactsAndContactGroups,
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
import { query } from '../FormInitialValues/useFormInitialValues';
import {
  findBusinessViewsEndpoint,
  resourceAccessRuleEndpoint
} from '../api/endpoints';

import {
  editedRuleFormData,
  editedRuleFormDataWithAllContactsAndContactGroups,
  editedRuleFormDataiWithAllBusinessViews,
  editedRuleFormDataiWithBusinessViews,
  findBusinessViewsResponse,
  findResourceAccessRuleResponse,
  findResourceAccessRuleResponseDecoded,
  platformVersions
} from './testUtils';

const store = createStore();
store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Edit });
store.set(editedResourceAccessRuleIdAtom, 1);
store.set(resourceAccessRulesNamesAtom, [
  { id: 1, name: 'Rule 1' },
  { id: 2, name: 'Rule 2' }
]);

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
  cy.stub(query, 'useQueryClient').returns({
    getQueryData: () => findResourceAccessRuleResponseDecoded()
  });

  cy.interceptAPIRequest({
    alias: 'findResourceAccessRuleRequest',
    method: Method.GET,
    path: resourceAccessRuleEndpoint({ id: 1 }),
    response: findResourceAccessRuleResponse()
  });

  cy.interceptAPIRequest({
    alias: 'editResourceAccessRuleRequest',
    method: Method.PUT,
    path: resourceAccessRuleEndpoint({ id: 1 }),
    response: { status: 'ok' }
  });

  cy.interceptAPIRequest({
    alias: 'findBusinessViewsEndpoint',
    method: Method.GET,
    path: `${findBusinessViewsEndpoint}**`,
    response: findBusinessViewsResponse
  });

  cy.mount({
    Component: <ModalWithQueryProvider />
  });
};

describe('Edit modal', () => {
  beforeEach(() => initialize());

  it('displays the edit resource access rule modal and control actions', () => {
    cy.findByText(labelEditResourceAccessRule).should('be.visible');
    cy.findByText(labelRuleProperies).should('be.visible');
    cy.findByText(labelAddResourceDatasets).should('be.visible');
    cy.findByRole('dialog').scrollTo('bottom');
    cy.findByText(labelContactsAndContactGroups).should('be.visible');
    cy.findByLabelText(labelExit).should('be.enabled');
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('ensures that the form handles an empty name field correctly by showing an error message and disabling the Save button as a validation measure', () => {
    cy.findByLabelText(labelName).clear();
    cy.findByText(labelRuleProperies).click();

    cy.findByText(labelRequired).should('be.visible');

    cy.findByRole('dialog').scrollTo('bottom');
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('ensures that the form handles an existing name field correctly by showing an error message and disabling the Save button as a validation measure', () => {
    cy.findByLabelText(labelName).clear().type('Rule 2');
    cy.findByText(labelRuleProperies).click();

    cy.findByText(labelNameAlreadyExists).should('be.visible');

    cy.findByRole('dialog').scrollTo('bottom');
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it("ensures that the Save's button initial state is set to disabled", () => {
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('confirms that the Save button becomes enabled when a modification occurs and the form is error-free', () => {
    cy.findByLabelText(labelSave).should('be.disabled');
    cy.findByLabelText(labelDescription).clear().type('Rule 1 description');

    cy.findByLabelText(labelSave).should('not.be.disabled');

    cy.makeSnapshot();
  });

  it('displays configured resources for the Resource Access Rule', () => {
    cy.findByText(labelAddResourceDatasets).should('be.visible');
    cy.findByText('Host group').should('be.visible');
    cy.findByText('Linux-Servers').should('be.visible');
    cy.findByText('Host').should('be.visible');
    cy.findByText('Centreon-Server').should('be.visible');

    cy.findByText('Service').should('be.visible');
    cy.findByText('Disk-/var').should('be.visible');
    cy.findByText('Disk-/usr').should('be.visible');
    cy.findByText('Disk-/opt').should('be.visible');
    cy.findByText('Disk-/').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays configured contacts and contact groups for the Resource Access Rule', () => {
    cy.findByRole('dialog').scrollTo('bottom');
    cy.findByText('admin admin').should('be.visible');
    cy.findByText('centreon-gorgone').should('be.visible');
    cy.findByText('Guest').should('be.visible');
    cy.findByText('Supervisor').should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to edit a Resource Access Rule when a configured value is changed and the Save button is clicked', () => {
    cy.findAllByTestId('DeleteOutlineIcon').last().click();

    cy.findByRole('dialog').scrollTo('bottom');
    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@editResourceAccessRuleRequest');

    cy.findByText(labelResourceAccessRuleEditedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to edit a Resource Access Rule when a configured resources are changed to All resources in datasets', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Edit });

    cy.findAllByTestId('DeleteOutlineIcon').last().click();

    cy.findAllByTestId('Delete').last().click();
    cy.findAllByLabelText(labelSelectResourceType).last().click();
    cy.findByText('All resources').click();
    cy.findByLabelText(labelAllResourcesSelected).should('be.visible');
    cy.findAllByTestId(labelSelectResource).should('be.disabled');

    cy.findByLabelText(labelAddFilter).should('be.disabled');
    cy.findByLabelText(labelAddNewDataset).should('be.disabled');
    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@editResourceAccessRuleRequest');

    cy.findByText(labelResourceAccessRuleEditedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to edit a Resource Access Rule when a configured resources are changed to All host groups in datasets', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Edit });

    cy.findAllByTestId('DeleteOutlineIcon').last().click();

    cy.findAllByTestId('Delete').last().click();

    cy.findByLabelText(labelName).clear().type('rule#1');

    cy.findByLabelText(labelAllHostGroups).click();
    cy.findByLabelText(labelAllHostGroupsSelected).should('be.visible');
    cy.findByLabelText(labelAllHostGroupsSelected).should('be.disabled');

    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@editResourceAccessRuleRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(editedRuleFormData);
    });

    cy.findByText(labelResourceAccessRuleEditedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('send a request to edit a Resource Access Rule when business views are added to configuration', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Edit });
    store.set(platformVersionsAtom, platformVersions);

    cy.findAllByTestId('DeleteOutlineIcon').last().click();

    cy.findAllByTestId('Delete').last().click();

    cy.findByLabelText(labelName).clear().type('rule#1');
    cy.findAllByLabelText(labelSelectResourceType).last().click();
    cy.findByText(labelBusinessView).click();

    cy.findAllByTestId(labelSelectResource).last().click();
    cy.waitForRequest('@findBusinessViewsEndpoint');
    cy.findByText('BV1').click();
    cy.findAllByTestId(labelSelectResource).last().click();
    cy.findByText('BV2').click();

    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@editResourceAccessRuleRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(
        editedRuleFormDataiWithBusinessViews
      );
    });

    cy.findByText(labelResourceAccessRuleEditedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('send a request to edit a Resource Access Rule when all business views are added to configuration', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Edit });
    store.set(platformVersionsAtom, platformVersions);

    cy.findAllByTestId('DeleteOutlineIcon').last().click();

    cy.findAllByTestId('Delete').last().click();

    cy.findByLabelText(labelName).clear().type('rule#1');
    cy.findAllByLabelText(labelSelectResourceType).last().click();
    cy.findByText(labelBusinessView).click();
    cy.findByText(labelAllBusinessViews).click();
    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@editResourceAccessRuleRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(
        editedRuleFormDataiWithAllBusinessViews
      );
    });

    cy.findByText(labelResourceAccessRuleEditedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to edit a Resource Access Rule when configured contacts and contact groups are changed to all', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Edit });

    cy.findAllByTestId('DeleteOutlineIcon').last().click();

    cy.findAllByTestId('Delete').last().click();

    cy.findByLabelText(labelName).clear().type('rule#1');

    cy.findByLabelText(labelAllHostGroups).click();
    cy.findByLabelText(labelAllHostGroupsSelected).should('be.visible');
    cy.findByLabelText(labelAllHostGroupsSelected).should('be.disabled');

    cy.findByLabelText(labelAllContacts).click();
    cy.findByLabelText(labelAllContactGroups).click();

    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@editResourceAccessRuleRequest').then(({ request }) => {
      expect(JSON.parse(request.body)).to.deep.equal(
        editedRuleFormDataWithAllContactsAndContactGroups
      );
    });

    cy.findByText(labelResourceAccessRuleEditedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a confirmation dialog when the form is edited and the Exit button is clicked', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Edit });

    cy.findAllByTestId('DeleteOutlineIcon').last().click();

    cy.findAllByTestId('Delete').last().click();
    cy.findByLabelText(labelExit).click();

    cy.findByText(labelYourFormHasUnsavedChanges).should('be.visible');
    cy.findByText(labelDoYouWantToQuitWithoutSaving).should('be.visible');

    cy.makeSnapshot();

    cy.findByText('Cancel').click();
  });

  it('displays a confirmation dialog when the form is edited and the Close button is clicked', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Edit });

    cy.findAllByTestId('DeleteOutlineIcon').last().click();

    cy.findAllByTestId('Delete').last().click();
    cy.findByLabelText('close').click();

    cy.findByText(labelYourFormHasUnsavedChanges).should('be.visible');
    cy.findByText(labelDoYouWantToQuitWithoutSaving).should('be.visible');

    cy.makeSnapshot();

    cy.findByText('Cancel').click();
  });

  it('displays a confiramtion dialog when the form is edited and a click occurs outside the modal', () => {
    store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Edit });

    cy.findAllByTestId('DeleteOutlineIcon').last().click();

    cy.findAllByTestId('Delete').last().click();
    cy.clickOutside();

    cy.findByText(labelYourFormHasUnsavedChanges).should('be.visible');
    cy.findByText(labelDoYouWantToQuitWithoutSaving).should('be.visible');

    cy.makeSnapshot();
  });
});
