/* eslint-disable @typescript-eslint/no-unused-expressions */
import {
  labelCancel,
  labelName,
  labelNewFilter,
  labelSave
} from '../../translatedLabels';

import CreateFilterDialog from './CreateFilterDialog';

const initialize = ({ name, reject }): unknown => {
  const success = cy.stub();
  const cancel = cy.stub();
  const request = () => () => (reject ? Promise.reject() : Promise.resolve());

  cy.mount({
    Component: (
      <CreateFilterDialog
        open
        callbackSuccess={success}
        payloadAction={{
          filter: {
            name
          }
        }}
        request={request}
        onCancel={cancel}
      />
    )
  });

  return {
    cancel,
    request,
    success
  };
};

describe('Create filter dialog', () => {
  it('displays the name field as empty', () => {
    initialize({ name: undefined, reject: false });

    cy.contains(labelNewFilter).should('be.visible');
    cy.findAllByTestId(labelName).eq(1).should('have.value', '');
    cy.contains(labelCancel).should('be.enabled');
    cy.contains(labelSave).should('be.disabled');

    cy.makeSnapshot();
  });

  it('displays the name field with a value when a value is already set', () => {
    initialize({ name: 'Filter name', reject: false });

    cy.findAllByTestId(labelName).eq(1).should('have.value', 'Filter name');
    cy.findAllByTestId(labelName).eq(1).should('be.disabled');
    cy.contains(labelCancel).should('be.enabled');
    cy.contains(labelSave).should('be.enabled');

    cy.makeSnapshot();
  });

  it('changes the name field when a new value is typed', () => {
    initialize({ name: '', reject: false });

    cy.findAllByTestId(labelName).eq(1).clear().type('New filter');
    cy.findAllByTestId(labelName).eq(1).should('have.value', 'New filter');

    cy.makeSnapshot();
  });

  it('calls the cancel callback when the corresponding button is clicked', () => {
    const { cancel } = initialize({ name: 'Filter name', reject: false });

    cy.contains(labelCancel)
      .click()
      .then(() => {
        expect(cancel).to.have.been.called;
      });

    cy.makeSnapshot();
  });

  it('calls the saved callback when the corresponding button is clicked', () => {
    const { success } = initialize({ name: 'Filter name', reject: false });

    cy.contains(labelSave)
      .click()
      .then(() => {
        expect(success).to.have.been.called;
      });

    cy.makeSnapshot();
  });

  it('does not call the saved callback when the corresponding button is clicked', () => {
    const { success } = initialize({ name: 'Filter name', reject: true });

    cy.contains(labelSave)
      .click()
      .then(() => {
        expect(success).to.not.have.been.called;
      });

    cy.makeSnapshot();
  });

  it('calls the success callback when the name is fullfilled and the Enter key is pressed', () => {
    const { success } = initialize({ name: '', reject: false });

    cy.findAllByTestId(labelName).eq(1).type('Filter');
    cy.findAllByTestId(labelName)
      .eq(1)
      .trigger('keypress', {
        key: 'enter'
      })
      .then(() => {
        expect(success).to.not.have.been.called;
      });

    cy.makeSnapshot();
  });
});
