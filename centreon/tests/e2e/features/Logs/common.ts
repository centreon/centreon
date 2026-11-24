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

const runQueryButton = (): Cypress.Chainable<JQuery<HTMLElement>> =>
  cy.getIframeBody().find('#div1 input[name="graph"]');

// Waits for a search intercepted as "@eventLogsSearch" to end. cy.wait() returns
// when the response arrives, before the list is rendered and the controls are
// enabled again: "Run query" is enabled again only once the search ends.
// Yields the interception, as cy.wait() does.
const waitForSearch = (): Cypress.Chainable<
  import('cypress/types/net-stubbing').Interception
> =>
  cy.wait('@eventLogsSearch').then((interception) => {
    runQueryButton().should('not.be.disabled');
    cy.wrap(interception, { log: false });
  });

interface EventLogsRowsProps {
  count: number;
  // 0 is a service alert, 4 an engine message.
  msgType: 0 | 4;
  output: string;
}

// Inserts the rows straight in the logs table: generating that many events
// through the monitoring engine would be slow. The newest row is the first one.
const buildInsertEventLogsQuery = ({
  count,
  msgType,
  output
}: EventLogsRowsProps): string => {
  const rows = Array.from(
    { length: count },
    (_, index) =>
      `(UNIX_TIMESTAMP() - ${index + 1}, 999999, 'e2e-host', 'Central', ${msgType}, ` +
      `'${output} ${index + 1}', 1, 'e2e-service', 999999, 2, 1, '', '')`
  );

  return (
    'INSERT INTO logs (ctime, host_id, host_name, instance_name, msg_type, output, retry, ' +
    'service_description, service_id, status, type, notification_cmd, notification_contact) ' +
    `VALUES ${rows.join(', ')}`
  );
};

export {
  buildInsertEventLogsQuery,
  openEventLogsPageAsRestrictedUser,
  openHostFilterDropdown,
  runQueryButton,
  waitForSearch
};
