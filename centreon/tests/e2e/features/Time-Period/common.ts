import { PAGES } from 'fixtures/shared/constants/pages';

const setTimePeriod = (): Cypress.Chainable => {
  cy.getSidePanelBody().find('input[name="tp_name"]').type('timePeriodName');
  cy.getSidePanelBody().find('input[name="tp_alias"]').type('timePeriodAlias');
  const weekdays = [
    'sunday',
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday'
  ];
  const timeRanges = {
    friday: '07:00-18:00',
    monday: '07:00-12:00,13:00-18:00',
    saturday: '10:00-16:00',
    sunday: '14:00-16:00',
    thursday: '14:00-16:00',
    tuesday: '07:00-18:00',
    wednesday: '07:00-12:00,13:00-17:00'
  };

  weekdays.forEach((day) => {
    cy.getSidePanelBody().find(`input[name="tp_${day}"]`).type(timeRanges[day]);
  });
  cy.getSidePanelBody().find('li#c2').click();
  cy.getSidePanelBody().contains('+ Add new entry').click();
  const exceptions = [
    { date: 'december 25', timeRange: '00:00-22:59,23:00-24:00' },
    { date: 'january 1', timeRange: '00:00-24:00' },
    { date: 'july 14', timeRange: '00:00-24:00' },
    { date: 'may 25', timeRange: '00:00-24:00' }
  ];

  exceptions.forEach((exception, index) => {
    if (index > 0) {
      cy.getSidePanelBody().contains('+ Add new entry').click();
    }
    cy.getSidePanelBody()
      .find(`input#exceptionInput_${index}`)
      .type(exception.date);
    cy.getSidePanelBody()
      .find(`input#exceptionTimerange_${index}`)
      .type(exception.timeRange);
  });

  // Return the last Cypress command to satisfy the return type
  return cy.getSidePanelBody();
};

/** Open the listing and wait for its first AJAX page to be rendered. */
function visitTimePeriodsListing() {
  cy.visit(PAGES.configuration.timePeriodsLegacy);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForListingRefresh();
}

/** Wait for the listing to swap its loading row for real rows. */
function waitForListingRefresh() {
  cy.getIframeBody()
    .find('#clTableBody tr td')
    .should('not.contain', 'Loading');
}

/**
 * The listing has liveSearch enabled, so typing is enough — there is no Search
 * button on this page (only the contacts listing, which has advanced filters,
 * renders one).
 */
function searchTimePeriod(term: string) {
  cy.getIframeBody().find('#clSearchInput').clear().type(term);
  waitForListingRefresh();
}

function navigateToTimePeriodsAndInitiateAddition() {
  visitTimePeriodsListing();
  cy.getIframeBody().find('a.cl-btn-add').click();
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
}

function submitForm() {
  cy.getSidePanelBody()
    .find('div#validForm')
    .find('p.oreonbutton')
    .find('.btc.bt_success[name="submitA"]')
    .click();
}

export {
  navigateToTimePeriodsAndInitiateAddition,
  searchTimePeriod,
  setTimePeriod,
  submitForm,
  visitTimePeriodsListing,
  waitForListingRefresh
};
