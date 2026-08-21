import { PAGES } from 'fixtures/shared/constants/pages';

/** Open the Administration > Logs listing and wait for its rows. */
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
 * badge, object-type label and author. `badgeState` is the badge colour class
 * (service_ok / service_warning / service_critical); `badgeLabel` is the
 * translated badge text (Added / Changed / ...); `typeLabel` is the translated
 * Object Type label (e.g. "Time period"), not the raw token. `author` defaults
 * to the admin the suite runs as: the column is resolved server-side, so leaving
 * it unasserted would let a failed lookup render "System" unnoticed.
 */
const assertLatestChangelogRow = (
  badgeState: string,
  badgeLabel: string,
  typeLabel: string,
  author = 'admin'
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
      cy.get('td').eq(5).should('contain.text', author);
    });
};

/** Click an object name in the listing to open its timeline detail page. */
const openObjectTimeline = (objectName: string): void => {
  // Exact match: a substring match opens a neighbouring row whose name merely
  // starts with this one ("host-category" vs "host-category-default"), and the
  // header guard below would still pass.
  cy.getIframeBody()
    .find('#clTableBody td a')
    .filter((_index, link) => Cypress.$(link).text().trim() === objectName)
    .first()
    .click();
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
