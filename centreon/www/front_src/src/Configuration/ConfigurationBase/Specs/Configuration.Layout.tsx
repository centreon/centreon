import { capitalize } from '@mui/material';
import pluralize from 'pluralize';

import {
  labelActions,
  labelAlias,
  labelDelete,
  labelDuplicate,
  labelEnableDisable,
  labelFilters,
  labelMoreActions,
  labelName
} from '../translatedLabels';

import initialize from './initialize';

export default (resourceType: string): void => {
  const capitalizedResourceType = capitalize(resourceType);
  const pluralizedResourceType = pluralize(capitalizedResourceType);

  describe('Layout', () => {
    beforeEach(() => {
      initialize({ resourceType });
    });

    it('renders the layout with all components', () => {
      cy.waitForRequest('@getAll');

      cy.contains(pluralizedResourceType).should('be.visible');

      cy.makeSnapshot(
        `${resourceType}: renders the layout with all components`
      );
    });

    it('displays configuration static columns', () => {
      cy.waitForRequest('@getAll');

      cy.contains(labelEnableDisable).should('be.visible');
      cy.contains(labelActions).should('be.visible');

      cy.get(`[data-testid="${labelDuplicate}_1"]`).should('be.visible');
      cy.get(`[data-testid="${labelDelete}_1"]`).should('be.visible');
      cy.get(`[data-testid="${labelEnableDisable}_1"]`).should('be.visible');
    });

    it('displays resource-specific additional columns', () => {
      cy.waitForRequest('@getAll');

      cy.contains(labelName).should('be.visible');
      cy.contains(labelAlias).should('be.visible');
    });

    it('displays more actions', () => {
      cy.waitForRequest('@getAll');

      cy.get(`[data-testid="${labelMoreActions}"]`).should('be.visible');
    });

    it('displays and interacts with filters', () => {
      cy.waitForRequest('@getAll');

      cy.get('[data-testid="search-bar"]').should('be.visible');

      cy.get(`[data-testid="${labelFilters}"]`).click();

      cy.get('[data-testid="advanced-filters"]').should('be.visible');

      cy.makeSnapshot(`${resourceType}: displays and interacts with filters`);
    });
  });
};
