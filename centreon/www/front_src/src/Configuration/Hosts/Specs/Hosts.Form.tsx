import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import initialize from './initialize';

// The surface is chosen by the module, and `ConfigurationBase`'s own specs pass
// `formVariant` explicitly — so this is the only place a revert to the modal
// would turn something red.
export default () => {
  describe('Form: ', () => {
    it('opens the form of a host in a side panel rather than a modal', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.contains('host 0').click();

      cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
        'be.visible'
      );
      cy.get('[data-testid="Modal"]').should('not.exist');

      // The listing row names the panel, without waiting for a detail call.
      cy.get(`[data-testid="${panelDataTestIds.header}"]`).should(
        'have.text',
        'host 0'
      );
    });

    it('opens the creation form in the same panel', () => {
      initialize({});

      cy.waitForRequest('@getAllHosts');

      cy.get('[data-testid="add-resource"]').click();

      cy.get(`[data-testid="${panelDataTestIds.content}"]`).should(
        'be.visible'
      );
      cy.get('[data-testid="Modal"]').should('not.exist');
    });
  });
};
