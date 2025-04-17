import { labelAlias, labelName } from '../translatedLabels';
import initialize from './initialize';

export default () => {
  describe('Filters: ', () => {
    it('displays and interacts with filters', () => {
      initialize({});

      cy.waitForRequest('@getAllHostGroups');

      cy.get('[data-testid="search-bar"]').should('be.visible');

      cy.get(`[data-testid="Filters"]`).click();

      cy.get('[data-testid="advanced-filters"]').should('be.visible');

      cy.contains(labelName).should('be.visible');
      cy.contains(labelAlias).should('be.visible');

      cy.makeSnapshot();
    });
  });
};
