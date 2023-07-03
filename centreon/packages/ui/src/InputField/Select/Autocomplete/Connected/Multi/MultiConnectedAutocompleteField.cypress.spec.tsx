import {
  Method,
  MultiConnectedAutocompleteField,
  TestQueryProvider
} from '@centreon/ui';

import { baseEndpoint, getEndpoint, label, placeholder } from './utils';

const optionOne = 'My Option 1';

describe('Multi connected autocomplete', () => {
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
          <div style={{ paddingTop: 20 }}>
            <MultiConnectedAutocompleteField
              field="host.name"
              getEndpoint={getEndpoint}
              label={label}
              placeholder={placeholder}
            />
          </div>
        </TestQueryProvider>
      )
    });
  });

  it('displays and hide the list when the user double-clicks on the input', () => {
    cy.contains(label).should('be.visible');

    cy.get('[data-testid="Multi Connected Autocomplete"]').as('input');

    cy.get('@input').click();

    cy.waitForRequest('@getListOptions');

    cy.get('@input').invoke('attr', 'placeholder').should('equal', placeholder);

    cy.findByRole('presentation').as('listOptions');

    cy.get('@listOptions').should('be.visible');

    cy.fixture('inputField/listOptions').then((optionsData) => {
      cy.get('@listOptions').within(() => {
        optionsData.result.forEach((option) => {
          cy.contains(option.name);
        });
      });
    });

    cy.matchImageSnapshot(
      'displays the list when the user clicks on the input'
    );

    cy.get('@input').click();

    cy.get('@listOptions').should('not.exist');

    cy.matchImageSnapshot('hide the list when the user clicks on the input');
  });

  it('displays exactly one option on the list when the user types that option', () => {
    cy.get('[data-testid="Multi Connected Autocomplete"]').as('input');

    cy.get('@input').click();

    cy.waitForRequest('@getListOptions');

    cy.findByRole('presentation').as('listOptions');

    cy.get('@listOptions').should('be.visible');

    cy.get('@input').type(optionOne);

    cy.waitForRequest('@getSearchedOption');

    cy.fixture('inputField/searchedOption').then((optionData) => {
      cy.get('@listOptions')
        .find('li')
        .should('have.length', optionData.result.length);
    });

    cy.get('@listOptions').within(() => {
      cy.contains(optionOne).should('be.visible');
    });

    cy.matchImageSnapshot();
  });

  it('displays all options on the list when the user searches for and selects an option."', () => {
    cy.get('[data-testid="Multi Connected Autocomplete"]').as('input');

    cy.get('@input').click();

    cy.waitForRequest('@getListOptions');

    cy.findByRole('presentation').as('listOptions');

    cy.get('@listOptions').should('be.visible');

    cy.get('@input').type(optionOne);

    cy.waitForRequest('@getSearchedOption');

    cy.fixture('inputField/searchedOption').then((optionData) => {
      cy.get('@listOptions')
        .find('li')
        .should('have.length', optionData.result.length);
    });

    cy.get('[type="checkbox"]').check();
    cy.get('@input')
      .parent()
      .within(() => {
        cy.contains(optionOne).should('be.visible');

        cy.get('[data-testid="CancelIcon"]').should('be.visible');
      });

    cy.waitForRequest('@getListOptions');

    cy.fixture('inputField/listOptions').then((optionsData) => {
      cy.get('@listOptions')
        .find('li')
        .should('have.length', optionsData.result.length);

      cy.get('@listOptions').within(() => {
        optionsData.result.forEach((option) => {
          cy.contains(option.name);
        });
      });
    });

    cy.matchImageSnapshot();
  });
});
