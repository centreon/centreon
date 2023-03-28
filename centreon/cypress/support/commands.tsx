/* eslint-disable @typescript-eslint/no-namespace */
import '@centreon/js-config/cypress/component/commands';
import '@testing-library/cypress/add-commands';

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

Cypress.Commands.add(
  'moveSortableElement',
  ({ ariaLabel, direction }): void => {
    const key = `{${direction}arrow}`;

    cy.findByLabelText(ariaLabel).type(' ', {
      force: true,
      scrollBehavior: false
    });
    cy.findAllByLabelText(ariaLabel).eq(-1).type(key, {
      scrollBehavior: false
    });
    cy.findAllByLabelText(ariaLabel).eq(-1).type(' ', {
      scrollBehavior: false
    });
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      moveSortableElement: ({ ariaLabel, direction }) => void;
      waitFiltersAndListingRequests: () => Cypress.Chainable;
    }
  }
}
