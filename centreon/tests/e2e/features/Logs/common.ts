import { PAGES } from 'fixtures/shared/constants/pages';

/** Open the (modernized) Administration > Logs listing and wait for its rows. */
const openChangelogListing = (): void => {
  cy.visit(PAGES.configuration.logsLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
};

/**
 * Assert that the most recent listing row carries the expected modification-type
 * badge and object-type label. `badgeState` is the badge colour class
 * (service_ok / service_warning / service_critical); `typeLabel` is the
 * translated Object Type label (e.g. "Time period"), not the raw token.
 */
const assertLatestChangelogRow = (
  badgeState: string,
  badgeLabel: string,
  typeLabel: string
): void => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .first()
    .within(() => {
      cy.get('td')
        .eq(2)
        .find(`span.badge.${badgeState}`)
        .should('contain.text', badgeLabel);
      cy.get('td').eq(3).should('contain.text', typeLabel);
    });
};

/** Click an object name in the listing to open its timeline detail page. */
const openObjectTimeline = (objectName: string): void => {
  cy.getIframeBody().find('#clTableBody td a').contains(objectName).click();
  cy.waitForElementInIframe('#main-content', '.cld-wrapper');
  cy.getIframeBody()
    .find('.cld-header-text h2')
    .should('contain.text', objectName);
};

const restrictedUserFixture = 'event-logs-restricted-user';

const openEventLogsPageAsRestrictedUser = (): void => {
  cy.logout();
  cy.loginByTypeOfUser({ jsonName: restrictedUserFixture, loginViaApi: false });
  cy.visit(PAGES.monitoring.eventLogsLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'select#host_filter');
};

const openHostFilterDropdown = (): void => {
  cy.getIframeBody()
    .find('select#host_filter')
    .siblings('span.select2-container')
    .click();
};

export {
  assertLatestChangelogRow,
  openChangelogListing,
  openEventLogsPageAsRestrictedUser,
  openHostFilterDropdown,
  openObjectTimeline
};
