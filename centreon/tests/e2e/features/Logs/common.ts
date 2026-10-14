import { PAGES } from 'fixtures/shared/constants/pages';

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

export { openEventLogsPageAsRestrictedUser, openHostFilterDropdown };
