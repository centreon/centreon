import { filter, propEq } from 'ramda';
import { ResourceType } from '../../models';

import initialize from './initialize';
import { filtersConfiguration } from './utils';

import {
  labelAlias,
  labelClear,
  labelDisabled,
  labelFilters,
  labelName,
  labelSearch
} from '../translatedLabels';

export default (resourceType: ResourceType) => {
  describe('Filters', () => {
    it('sends a listing request with the name filter when the search bar is manually updated, debounced by 500ms', () => {
      initialize({ resourceType });

      cy.waitForRequest('@getAll');

      cy.findAllByPlaceholderText(labelSearch)
        .clear()
        .type(`${resourceType} 1`);

      cy.wait(500);

      cy.waitForRequest('@getAll').then(({ request }) => {
        expect(
          JSON.parse(request.url.searchParams.get('search'))
        ).to.deep.equal({
          $and: [{ $or: [{ name: { $rg: `${resourceType} 1` } }] }]
        });
      });

      cy.makeSnapshot(
        `${resourceType}: sends a listing request with name filter when the search bar is filled and enter key is triggered`
      );
    });

    it('sends a listing request when fields are filled and the search button is clicked', () => {
      initialize({ resourceType });

      cy.waitForRequest('@getAll');

      cy.get(`[data-testid="${labelFilters}"]`).click();
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

      cy.makeSnapshot(
        `${resourceType}: sends a listing request when fields are filled and the search button is clicked`
      );
    });

    it('clears all applied filters and sends a listing request with empty search parameters when the clear button is clicked', () => {
      initialize({ resourceType });

      cy.waitForRequest('@getAll');

      cy.get(`[data-testid="${labelFilters}"]`).click();
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

      cy.findByTestId(labelClear).click();

      cy.waitForRequest('@getAll').then(({ request }) => {
        expect(
          JSON.parse(request.url.searchParams.get('search'))
        ).to.deep.equal({ $and: [] });
      });

      cy.makeSnapshot(
        `${resourceType}: clears all applied filters and sends a listing request with empty search parameters when the clear button is clicked`
      );
    });

    it('hides the advanced filters icon when only the name field is filterable', () => {
      const onlyNameFilter = filter(
        propEq('name', 'fieldName'),
        filtersConfiguration
      );

      initialize({
        resourceType,
        filters: onlyNameFilter
      });

      cy.waitForRequest('@getAll');

      cy.get(`[data-testid="${labelFilters}"]`).should('not.exist');

      cy.makeSnapshot(
        `${resourceType}: hides the advanced filters icon when only the name field is filterable'`
      );
    });
  });
};
