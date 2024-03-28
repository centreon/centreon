/* eslint-disable @typescript-eslint/no-namespace */
import '@centreon/js-config/cypress/component/commands';
import '@testing-library/cypress/add-commands';

import React from 'react';

import dayjs from 'dayjs';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import isToday from 'dayjs/plugin/isToday';
import isYesterday from 'dayjs/plugin/isYesterday';
import weekday from 'dayjs/plugin/weekday';
import isBetween from 'dayjs/plugin/isBetween';
import isSameOrBefore from 'dayjs/plugin/isSameOrBefore';
import duration from 'dayjs/plugin/duration';
import { equals } from 'ramda';

import { SearchParameter } from '@centreon/ui';
import { SortQueryParameterValue } from '@centreon/ui/src/api/buildListingEndpoint/models';

interface Query {
  key: string;
  value: string | SearchParameter | SortQueryParameterValue;
}

interface WaitForRequestAndVerifyQueries {
  queries: Array<Query>;
  requestAlias: string;
}

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(isToday);
dayjs.extend(isYesterday);
dayjs.extend(weekday);
dayjs.extend(isBetween);
dayjs.extend(isSameOrBefore);
dayjs.extend(duration);

Cypress.Commands.add('displayFilterMenu', () => {
  cy.get('[aria-label="Filter options"]').click();

  cy.contains('Type').should('be.visible').click();
});

Cypress.Commands.add('clickOutside', () => {
  cy.get('body').click(0, 0);
});

Cypress.Commands.add('waitFiltersAndListingRequests', () => {
  cy.waitForRequest('@filterRequest');
  cy.waitForRequest('@dataToListingTable');
});

Cypress.Commands.add('render', (Component) => {
  cy.mount({
    Component: <Component />
  });

  cy.viewport(1200, 1000);
});

Cypress.Commands.add(
  'waitForRequestAndVerifyQueries',
  ({ requestAlias, queries }: WaitForRequestAndVerifyQueries) => {
    cy.waitForRequest(`@${requestAlias}`).then(({ request }) => {
      queries.forEach(({ key, value }) => {
        if (equals(typeof value, 'string')) {
          expect(request?.url?.searchParams.get(key)).to.equal(value);

          return;
        }
        expect(request?.url?.searchParams.get(key)).to.equal(
          JSON.stringify(value)
        );
      });
    });
  }
);

Cypress.Commands.add('waitForRequestAndVerifyBody', (requestAlias, body) => {
  cy.waitForRequest(`@${requestAlias}`).then(({ request }) => {
    expect(JSON.parse(request.body)).to.deep.equal(body);
  });
});

Cypress.Commands.add('openCalendar', (testId) => {
  cy.findByTestId(testId).then(($input) => {
    if ($input.attr('readonly')) {
      cy.wrap($input).click();
    } else {
      cy.findByTestId('CalendarIcon').click();
    }
  });
});

declare global {
  namespace Cypress {
    interface Chainable {
      clickOutside: () => Cypress.Chainable;
      openCalendar: (testId: string) => Cypress.Chainable;
      render: (options) => Cypress.Chainable;
      waitFiltersAndListingRequests: () => Cypress.Chainable;
      waitForRequestAndVerifyBody: (requestAlias, body) => Cypress.Chainable;
      waitForRequestAndVerifyQueries: (
        props: WaitForRequestAndVerifyQueries
      ) => Cypress.Chainable;
    }
  }
}
