import { filter, propEq } from 'ramda';
import { labelFilters } from '../translatedLabels';
import initialize from './initialize';
import { filtersConfiguration } from './utils';

export default (resourceType) => {
  describe('Filters', () => {
    it('search bar', () => {
      initialize({ resourceType });

      cy.get(`[data-testid="${labelFilters}"]`).should('be.visible');

      cy.matchImageSnapshot();
    });

    it('initial value', () => {});
    it('advance filters icon must not be visible if only the name filed is filterable', () => {
      const onlyNameFilter = filter(
        propEq('name', 'fieldName'),
        filtersConfiguration
      );

      initialize({
        resourceType,
        filters: onlyNameFilter
      });

      cy.get(`[data-testid="${labelFilters}"]`).should('not.exist');

      cy.matchImageSnapshot();
    });
  });
};
