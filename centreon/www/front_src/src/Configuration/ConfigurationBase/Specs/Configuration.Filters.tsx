import { filter, propEq } from 'ramda';

import { ResourceType } from '../../models';
import {
  labelAlias,
  labelClear,
  labelDisabled,
  labelFilters,
  labelName,
  labelSearch
} from '../translatedLabels';
import initialize from './initialize';
import {
  filtersConfiguration,
  filtersConfigurationWithSingleConnectedAutocomplete,
  filtersInitialValuesWithSingleConnectedAutocomplete,
  labelHostTemplate
} from './utils';

const initializeWithSingleConnectedAutocomplete = (
  resourceType: ResourceType
): void =>
  initialize({
    filters: filtersConfigurationWithSingleConnectedAutocomplete,
    initialValues: filtersInitialValuesWithSingleConnectedAutocomplete,
    resourceType
  });

const openAdvancedFilters = (): void => {
  cy.get(`[data-testid="${labelFilters}"]`).click();
  cy.get('[data-testid="advanced-filters"]').should('be.visible');
};

const selectHostTemplate = (name: string): void => {
  cy.findByTestId(labelHostTemplate).click();
  cy.waitForRequest('@getHostTemplates');
  cy.contains(name).click();
};

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

    describe('Single connected autocomplete', () => {
      beforeEach(() => {
        cy.clearLocalStorage();
      });

      afterEach(() => {
        cy.clearLocalStorage();
      });

      it('sends a listing request matching the selected id when a value is selected in the single connected autocomplete filter', () => {
        initializeWithSingleConnectedAutocomplete(resourceType);

        cy.waitForRequest('@getAll');

        openAdvancedFilters();

        selectHostTemplate('host template 1');

        cy.findByTestId(labelSearch).click();

        cy.waitForRequest('@getAll').then(({ request }) => {
          expect(
            JSON.parse(request.url.searchParams.get('search'))
          ).to.deep.equal({
            $and: [{ $or: [{ 'host_template.id': { $eq: 1 } }] }]
          });
        });

        cy.makeSnapshot(
          `${resourceType}: sends a listing request matching the selected id when a value is selected in the single connected autocomplete filter`
        );
      });

      it('removes the filter from the search parameters when the selected value is cleared', () => {
        initializeWithSingleConnectedAutocomplete(resourceType);

        cy.waitForRequest('@getAll');

        openAdvancedFilters();

        selectHostTemplate('host template 1');

        cy.findByTestId(labelHostTemplate)
          .closest('.MuiAutocomplete-root')
          .find('button[title="Clear"]')
          .click({ force: true });

        cy.findByTestId(labelHostTemplate).should('have.value', '');

        cy.findByTestId(labelSearch).click();

        cy.waitForRequest('@getAll').then(({ request }) => {
          expect(
            JSON.parse(request.url.searchParams.get('search'))
          ).to.deep.equal({ $and: [] });
        });

        cy.makeSnapshot(
          `${resourceType}: removes the filter from the search parameters when the selected value is cleared`
        );
      });

      it('keeps the selected value when the listing is mounted again', () => {
        initializeWithSingleConnectedAutocomplete(resourceType);

        cy.waitForRequest('@getAll');

        openAdvancedFilters();

        selectHostTemplate('host template 1');

        cy.findByTestId(labelHostTemplate).should(
          'have.value',
          'host template 1'
        );

        initializeWithSingleConnectedAutocomplete(resourceType);

        cy.waitForRequest('@getAll');

        openAdvancedFilters();

        cy.findByTestId(labelHostTemplate).should(
          'have.value',
          'host template 1'
        );

        cy.makeSnapshot(
          `${resourceType}: keeps the selected value when the listing is mounted again`
        );
      });
    });

    it('hides the advanced filters icon when only the name field is filterable', () => {
      const onlyNameFilter = filter(
        propEq('name', 'fieldName'),
        filtersConfiguration
      );

      initialize({
        filters: onlyNameFilter,
        resourceType
      });

      cy.waitForRequest('@getAll');

      cy.get(`[data-testid="${labelFilters}"]`).should('not.exist');

      cy.makeSnapshot(
        `${resourceType}: hides the advanced filters icon when only the name field is filterable'`
      );
    });
  });
};
