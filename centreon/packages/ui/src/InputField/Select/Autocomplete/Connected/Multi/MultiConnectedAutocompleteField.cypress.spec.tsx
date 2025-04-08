import {
  Method,
  MultiConnectedAutocompleteField,
  TestQueryProvider
} from '@centreon/ui';

import i18next from 'i18next';
import { useState } from 'react';
import { initReactI18next } from 'react-i18next';
import { labelSelectAll, labelUnSelectAll } from '../../../../translatedLabels';
import { baseEndpoint, getEndpoint, label, placeholder } from './utils';

const optionOne = 'My Option 1';

const Component = () => {
  const [values, setValues] = useState([]);
  return (
    <TestQueryProvider>
      <div style={{ paddingTop: 20 }}>
        <MultiConnectedAutocompleteField
          field="host.name"
          getEndpoint={getEndpoint}
          label={label}
          placeholder={placeholder}
          value={values}
          onChange={(_, item) => setValues(item)}
          disableSelectAll={false}
        />
      </div>
    </TestQueryProvider>
  );
};

describe('Multi connected autocomplete', () => {
  beforeEach(() => {
    i18next.use(initReactI18next).init({
      lng: 'en',
      resources: {}
    });

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
      Component: <Component />
    });
  });

  it('displays and hides the list when the user double-clicks on the input', () => {
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

    cy.makeSnapshot('displays the list when the user clicks on the input');

    cy.get('@input').click();

    cy.get('@listOptions').should('not.exist');

    cy.makeSnapshot('hides the list when the user clicks on the input');
  });

  it('displays all options on the list when the user searches for and selects an option', () => {
    cy.get('[data-testid="Multi Connected Autocomplete"]').as('input');

    cy.get('@input').click();

    cy.waitForRequest('@getListOptions');

    cy.findByRole('presentation').as('listOptions');

    cy.get('@listOptions').should('be.visible');

    cy.get('@input').type(optionOne);

    cy.waitForRequest('@getSearchedOption');

    cy.fixture('inputField/searchedOption').then(() => {
      cy.get('@listOptions').find('li').should('have.length', 6);
    });

    cy.get('[type="checkbox"]').eq(0).check();
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
        .should('have.length', optionsData.result.length + 1);

      cy.get('@listOptions').within(() => {
        optionsData.result.forEach((option) => {
          cy.contains(option.name);
        });
      });
    });
  });

  it('checks all options when Select all button is clicked', () => {
    cy.get('[data-testid="Multi Connected Autocomplete"]').as('input');

    cy.get('@input').click();

    cy.contains('5 element(s) found');

    cy.waitForRequest('@getListOptions');

    cy.contains(labelSelectAll).click();

    cy.contains(labelUnSelectAll).should('be.visible');

    cy.get('[data-testid="CancelIcon"]').should('have.length', 5);

    cy.makeSnapshot('checks all options when Select all button is clicked');
  });

  it('unchecks all options when unSelect all button is clicked', () => {
    cy.get('[data-testid="Multi Connected Autocomplete"]').as('input');

    cy.get('@input').click();

    cy.contains('5 element(s) found');

    cy.waitForRequest('@getListOptions');

    cy.contains(labelSelectAll).click();
    cy.contains(labelUnSelectAll).click();

    cy.contains(labelSelectAll).should('be.visible');

    cy.get('[data-testid="CancelIcon"]').should('have.length', 0);

    cy.makeSnapshot('unchecks all options when unSelect all button is clicked');
  });
});
