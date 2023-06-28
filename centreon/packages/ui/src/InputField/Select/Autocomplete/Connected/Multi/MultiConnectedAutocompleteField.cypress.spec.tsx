import { equals } from 'ramda';

import {
  Method,
  MultiConnectedAutocompleteField,
  TestQueryProvider
} from '@centreon/ui';

import { baseEndpoint, getEndpoint, label, placeholder } from './utils';

const optionOne = 'My Option 1';

describe('Multi connected', () => {
  beforeEach(() => {
    cy.fixture('inputField/listOptions').then((optionsData) => {
      cy.interceptAPIRequest({
        alias: 'getListOptions',
        method: Method.GET,
        path: `${baseEndpoint}**`,
        response: optionsData
      });
    });

    cy.fixture('inputField/searchedOption').then((optionData) => {
      cy.interceptAPIRequest({
        alias: 'getSearchedOption',
        method: Method.GET,
        path: `${baseEndpoint}**`,
        query: {
          name: 'search',
          value: JSON.stringify({
            $and: [{ 'host.name': { $lk: `%${optionOne}%` } }]
          })
        },
        response: optionData
      });
    });

    cy.mount({
      Component: (
        <TestQueryProvider>
          <MultiConnectedAutocompleteField
            field="host.name"
            getEndpoint={getEndpoint}
            label={label}
            placeholder={placeholder}
          />
        </TestQueryProvider>
      )
    });
  });

  it('first test , afficher et cacher la liste quand user clique 2 fois sur l input', () => {
    cy.contains(label).should('be.visible');
    cy.get('[data-testid="Multi Connected Autocomplete"]').as('input');
    cy.get('@input').click();
    cy.waitForRequest('@getListOptions');
    cy.get('@input').invoke('attr', 'placeholder').should('equal', placeholder);

    cy.get('[data-testid="listOptions"]').as('listOptions');

    cy.get('@listOptions').should('be.visible');

    cy.fixture('inputField/options').then((optionsData) => {
      optionsData.result.forEach((option) => {
        cy.contains(option.name);
      });
    });
    cy.get('@input').click();
    cy.get('@listOptions').should('not.exist');
  });

  it.only('second test , chercher extactement une option , la liste doit avoir que cette option', () => {
    cy.get('[data-testid="Multi Connected Autocomplete"]').as('input');
    cy.get('@input').click();
    cy.waitForRequest('@getListOptions');

    cy.get('[data-testid="listOptions"]').as('listOptions');

    cy.get('@listOptions').should('be.visible');

    cy.get('@input').type(optionOne);
    cy.waitForRequest('@getSearchedOption');

    cy.fixture('inputField/listOptions').then((optionsData) => {
      optionsData.result.forEach((option, index) => {
        if (equals(index, 0)) {
          cy.contains(option.name).should('be.visible');
        }
        if (!equals(index, 0)) {
          cy.contains(option.name).should('not.exist');
        }
      });
    });
  });

  // it('troisieme  test , chercher l option , selectionner l option , l optipo doit etre visible sur l input', () => {
  //   cy.contains(label).should('be.visible');
  //   cy.get('[data-testid="Multi Connected Autocomplete"]').as('input');
  //   cy.get('@input').click();
  //   cy.waitForRequest('@getListOptions');
  //   cy.get('@input').invoke('attr', 'placeholder').should('equal', placeholder);

  //   cy.get('[data-testid="listOptions"]').as('listOptions');

  //   cy.get('@listOptions').should('be.visible');
  // });

  // it('quatrie, test , chercher l option , selectionner l option , l optipo doit etre visible sur l input , la liste doit avoir les autres options', () => {
  //   cy.contains(label).should('be.visible');
  //   cy.get('[data-testid="Multi Connected Autocomplete"]').as('input');
  //   cy.get('@input').click();
  //   cy.waitForRequest('@getListOptions');
  //   cy.get('@input').invoke('attr', 'placeholder').should('equal', placeholder);

  //   cy.get('[data-testid="listOptions"]').as('listOptions');

  //   cy.get('@listOptions').should('be.visible');
  // });
});
