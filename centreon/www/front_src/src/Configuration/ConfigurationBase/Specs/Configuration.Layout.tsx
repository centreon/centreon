import { capitalize } from '@mui/material';
import pluralize from 'pluralize';

import {
  labelActions,
  labelAlias,
  labelDelete,
  labelDuplicate,
  labelEnableDisable,
  labelFilters,
  labelName
} from '../translatedLabels';

import initialize from './initialize';

export default (resourceType): void => {
  describe('Layout', () => {
    beforeEach(() => initialize({ resourceType }));

    it('render the layout with all components', () => {
      cy.contains(pluralize(capitalize(resourceType)));

      cy.matchImageSnapshot();
    });
    it('static columns', () => {
      cy.contains(labelEnableDisable).should('be.visible');
      cy.contains(labelActions).should('be.visible');

      cy.get(`[data-testid="${labelDuplicate}_1"]`).should('be.visible');
      cy.get(`[data-testid="${labelDelete}_1"]`).should('be.visible');
      cy.get(`[data-testid="${labelEnableDisable}_1"]`).should('be.visible');
    });
    it('additional columns', () => {
      cy.contains(labelName).should('be.visible');
      cy.contains(labelAlias).should('be.visible');
    });
    it('massive action', () => {
      cy.get(`[data-testid="${labelDuplicate}"]`).should('be.visible');
      cy.get(`[data-testid="${labelDelete}"]`).should('be.visible');
    });
    it('filters', () => {
      cy.get(`[data-testid="search-bar"]`).should('be.visible');

      cy.get(`[data-testid="${labelFilters}"]`).eq(1).click();

      cy.get(`[data-testid="advanced-filters"]`).should('be.visible');

      cy.matchImageSnapshot();
    });
  });
};
