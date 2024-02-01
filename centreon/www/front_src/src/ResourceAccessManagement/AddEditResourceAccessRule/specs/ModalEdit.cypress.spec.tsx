import { ReactElement } from 'react';

import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import {
  editedResourceAccessRuleIdAtom,
  modalStateAtom,
  resourceAccessRulesNamesAtom
} from '../../atom';
import { AddEditResourceAccessRuleModal } from '..';
import { ModalMode } from '../../models';
import { resourceAccessRuleEndpoint } from '../api/endpoints';
import {
  labelContactsAndContactGroups,
  labelDescription,
  labelEditResourceAccessRule,
  labelExit,
  labelName,
  labelNameAlreadyExists,
  labelRequired,
  labelResourceAccessRuleEditedSuccess,
  labelResourceSelection,
  labelRuleProperies,
  labelSave
} from '../../translatedLabels';

import { findResourceAccessRuleResponse } from './testUtils';

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

  cy.mount({
    Component: <ModalWithQueryProvider />
  });
};

describe('Edit modal', () => {
  beforeEach(() => initialize());

  it('displays the edit resource access rule modal and control actions', () => {
    cy.waitForRequest('@findResourceAccessRuleRequest');

    cy.findByText(labelEditResourceAccessRule).should('be.visible');
    cy.findByText(labelRuleProperies).should('be.visible');
    cy.findByText(labelResourceSelection).should('be.visible');
    cy.findByRole('dialog').scrollTo('bottom');
    cy.findByText(labelContactsAndContactGroups).should('be.visible');
    cy.findByLabelText(labelExit).should('be.enabled');
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('ensures that the form handles an empty name field correctly by showing an error message and disabling the Save button as a validation measure', () => {
    cy.waitForRequest('@findResourceAccessRuleRequest');

    cy.findByLabelText(labelName).clear();
    cy.findByText(labelRuleProperies).click();

    cy.findByText(labelRequired).should('be.visible');

    cy.findByRole('dialog').scrollTo('bottom');
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('ensures that the form handles an existing name field correctly by showing an error message and disabling the Save button as a validation measure', () => {
    cy.waitForRequest('@findResourceAccessRuleRequest');

    cy.findByLabelText(labelName).clear().type('Rule 2');
    cy.findByText(labelRuleProperies).click();

    cy.findByText(labelNameAlreadyExists).should('be.visible');

    cy.findByRole('dialog').scrollTo('bottom');
    cy.findByLabelText(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it("ensures that the Save's button initial state is set to disabled", () => {
    cy.waitForRequest('@findResourceAccessRuleRequest');

    cy.findByLabelText(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('confirms that the Save button becomes enabled when a modification occurs and the form is error-free', () => {
    cy.waitForRequest('@findResourceAccessRuleRequest');

    cy.findByLabelText(labelSave).should('be.disabled');
    cy.findByLabelText(labelDescription).clear().type('Rule 1 description');

    cy.findByLabelText(labelSave).should('not.be.disabled');

    cy.makeSnapshot();
  });

  it('displays configured resources for the Resource Access Rule', () => {
    cy.waitForRequest('@findResourceAccessRuleRequest');

    cy.findByText(labelResourceSelection).should('be.visible');
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
    cy.waitForRequest('@findResourceAccessRuleRequest');

    cy.findByRole('dialog').scrollTo('bottom');
    cy.findByText('admin admin').should('be.visible');
    cy.findByText('centreon-gorgone').should('be.visible');
    cy.findByText('Guest').should('be.visible');
    cy.findByText('Supervisor').should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to edit a Resource Access Rule when a configured value is changed and the Save button is clicked', () => {
    cy.waitForRequest('@findResourceAccessRuleRequest');

    cy.findAllByTestId('DeleteOutlineIcon').last().click();

    cy.findByRole('dialog').scrollTo('bottom');
    cy.findByLabelText(labelSave).click();

    cy.waitForRequest('@editResourceAccessRuleRequest');

    cy.findByText(labelResourceAccessRuleEditedSuccess).should('be.visible');

    cy.makeSnapshot();
  });
});
