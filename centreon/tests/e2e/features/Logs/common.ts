import { PAGES } from 'fixtures/shared/constants/pages';

const restrictedUserFixture = 'event-logs-restricted-user';

const eventLogsExportFilters = {
  alert: 'true',
  critical: 'true',
  down: 'true',
  error: 'true',
  export: '1',
  notification: 'true',
  oh: 'false',
  ok: 'true',
  output: '',
  period: '86400',
  search_H: 'false',
  search_S: 'false',
  unknown: 'true',
  unreachable: 'true',
  up: 'true',
  warning: 'true'
};

interface ExportEventLogsProps {
  engine?: boolean;
  selection?: string;
}

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

const exportEventLogsAsCsv = ({
  engine = false,
  selection = ''
}: ExportEventLogsProps): Cypress.Chainable<Cypress.Response<string>> =>
  cy.request({
    failOnStatusCode: false,
    qs: {
      ...eventLogsExportFilters,
      ...(engine ? { engine: 'true' } : {}),
      id: selection
    },
    url: PAGES.monitoring.eventLogsCsvExport
  });

const getCsvEventRows = (csv: string): Array<Array<string>> =>
  csv
    .split('\n')
    .filter((line) => /^\d{4}\/\d{2}\/\d{2};/.test(line))
    .map((line) => line.split(';'));

const buildMetaServiceSelectorQuery = (): string =>
  `SELECT ms.meta_id, h.host_id, s.service_id
    FROM meta_service ms
    INNER JOIN service s
      ON s.service_description = CONCAT('meta_', ms.meta_id) AND s.service_register = '2'
    INNER JOIN host_service_relation hsr ON hsr.service_service_id = s.service_id
    INNER JOIN host h ON h.host_id = hsr.host_host_id
    WHERE ms.meta_name = ?`;

const buildMetaServiceEventCountQuery = (): string =>
  `SELECT COUNT(*) AS count_events
    FROM logs
    WHERE host_name = '_Module_Meta' AND service_description = ? AND msg_type = 0`;

const engineCommandFile = '/var/lib/centreon-engine/rw/centengine.cmd';

// The submit results API ignores meta-services: write to the engine command file.
const buildSubmitCriticalMetaServiceResultCommand = (
  serviceDescription: string
): string =>
  [
    `ENABLE_PASSIVE_SVC_CHECKS;_Module_Meta;${serviceDescription}`,
    `PROCESS_SERVICE_CHECK_RESULT;_Module_Meta;${serviceDescription};2;event logs export`
  ]
    .map(
      (engineCommand) =>
        `echo "[$(date +%s)] ${engineCommand}" > ${engineCommandFile}`
    )
    .join(' && ');

export {
  buildMetaServiceEventCountQuery,
  buildMetaServiceSelectorQuery,
  buildSubmitCriticalMetaServiceResultCommand,
  exportEventLogsAsCsv,
  getCsvEventRows,
  openEventLogsPageAsRestrictedUser,
  openHostFilterDropdown
};
