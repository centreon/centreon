import { labelActions, labelEnableDisable } from '../translatedLabels';
import initialize from './initialize';

// The shared per-row column and the toggle are the two cells a module does not
// write itself, so what they render is decided entirely by the actions it
// declares. These pin that contract, which three modules depend on.
export default () => {
  describe('Static columns', () => {
    it('offers neither column when the module declares no row action', () => {
      initialize({ actions: { edit: true } });

      cy.waitForRequest('@getAll');

      cy.contains(labelActions).should('not.exist');
      cy.contains(labelEnableDisable).should('not.exist');

      cy.makeSnapshot();
    });

    it('offers the actions column, and not the toggle, for delete alone', () => {
      initialize({ actions: { delete: () => true, edit: true } });

      cy.waitForRequest('@getAll');

      cy.contains(labelActions).should('be.visible');
      cy.contains(labelEnableDisable).should('not.exist');
    });

    it('hides the toggle on rows the predicate refuses', () => {
      initialize({
        actions: { edit: true, enableDisable: ({ id }) => id === 1 }
      });

      cy.waitForRequest('@getAll');

      cy.findByTestId(`${labelEnableDisable}_1`).should('exist');
      cy.findByTestId(`${labelEnableDisable}_2`).should('not.exist');

      cy.makeSnapshot();
    });

    it('disables those rows instead when the module keeps them without write access', () => {
      initialize({
        actions: {
          edit: true,
          enableDisable: ({ id }) => id === 1,
          rowActionsWithoutWriteAccess: true
        }
      });

      cy.waitForRequest('@getAll');

      cy.findByTestId(`${labelEnableDisable}_1`)
        .find('input')
        .should('be.enabled');
      cy.findByTestId(`${labelEnableDisable}_2`)
        .find('input')
        .should('be.disabled');

      cy.makeSnapshot();
    });

    it('renders a module row action, filtered by its own visibility predicate', () => {
      initialize({
        actions: {
          edit: true,
          rowActions: [
            {
              dataTestId: ({ id }) => `custom-action_${id}`,
              Icon: () => <span>action</span>,
              isVisible: ({ id }) => id === 1,
              label: 'Custom action',
              onClick: () => undefined
            }
          ]
        }
      });

      cy.waitForRequest('@getAll');

      cy.findByTestId('custom-action_1').should('exist');
      cy.findByTestId('custom-action_2').should('not.exist');
    });
  });
};
