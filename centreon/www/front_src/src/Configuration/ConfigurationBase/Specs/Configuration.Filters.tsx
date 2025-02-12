import { filter, propEq } from 'ramda';
import {
  labelAlias,
  labelDisabled,
  labelFilters,
  labelName,
  labelSearch
} from '../translatedLabels';
import initialize from './initialize';
import { filtersConfiguration } from './utils';

export default (resourceType) => {
  describe('Filters', () => {
    it.only('send a listing request with name filter when the search bar filled and enter key triggerd', () => {
      initialize({ resourceType });

      cy.waitForRequest('@getAll');

      cy.findAllByPlaceholderText(labelSearch)
        .eq(1)
        .clear()
        .type(`${resourceType} 1`)
        .type('{enter}');

      cy.waitForRequest('@getAll').then(({ request }) => {
        expect(
          JSON.parse(request.url.searchParams.get('search'))
        ).to.deep.equal({
          $and: [{ $or: [{ name: { $rg: `${resourceType} 1` } }] }]
        });
      });

      cy.matchImageSnapshot();
    });

    it.only('send a listing request when filds are filled and the search button was clicked', () => {
      initialize({ resourceType });

      cy.waitForRequest('@getAll');

      cy.get(`[data-testid="${labelFilters}"]`).eq(1).click();

      cy.get('[data-testid="advanced-filters"]').should('be.visible');

      cy.get(`[data-testid="${labelName}"]`)
        .eq(1)
        .clear()
        .type(`${resourceType} 1`);
      cy.get(`[data-testid="${labelAlias}"]`)
        .eq(1)
        .clear()
        .type(`${resourceType} alias 1`);

      cy.findByTestId(labelDisabled).click();

      cy.findByTestId(labelSearch).click();

      cy.waitForRequest('@getAll').then(({ request }) => {
        expect(
          JSON.parse(request.url.searchParams.get('search'))
        ).to.deep.equal({
          $and: [
            { $or: [{ is_activated: { $eq: false } }] },
            { $or: [{ name: { $rg: `${resourceType} 1` } }] },
            { $or: [{ alias: { $rg: `${resourceType} alias 1` } }] }
          ]
        });
      });

      cy.matchImageSnapshot();
    });

    it.only('advance filters icon must not be visible if only the name filed is filterable', () => {
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
