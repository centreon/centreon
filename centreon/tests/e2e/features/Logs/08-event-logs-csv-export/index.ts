import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  buildMetaServiceEventCountQuery,
  buildMetaServiceSelectorQuery,
  buildSubmitCriticalMetaServiceResultCommand,
  exportEventLogsAsCsv,
  getCsvEventRows
} from '../common';

interface MetaServiceSelector {
  hostId: number;
  metaId: number;
  serviceId: number;
}

const metaServiceNames = {
  first: 'event-logs-export-meta-1',
  second: 'event-logs-export-meta-2'
};

const exportUserAclProfile =
  'resources/clapi/config-ACL/event-logs-export-acl-user.json';
const noAccessUserAclProfile =
  'resources/clapi/config-ACL/event-logs-no-access-acl-user.json';

const metaServiceSelectors: Record<string, MetaServiceSelector> = {};

const getMetaServiceSelector = (position: string): MetaServiceSelector => {
  const selector = metaServiceSelectors[metaServiceNames[position]];

  if (selector === undefined) {
    throw new Error(`No meta-service is configured as the ${position} one`);
  }

  return selector;
};

const toExportSelection = ({
  hostId,
  serviceId
}: MetaServiceSelector): string => `MS_${hostId}_${serviceId}`;

const loginAs = (jsonName: string): void => {
  cy.clearCookies();
  cy.loginByTypeOfUser({ jsonName, loginViaApi: true });
};

const generateMetaServiceEvent = (name: string): void => {
  cy.requestOnDatabase({
    database: 'centreon',
    params: [name],
    query: buildMetaServiceSelectorQuery()
  }).then(([rows]) => {
    expect(rows, `configuration of ${name}`).to.have.length(1);

    const selector: MetaServiceSelector = {
      hostId: rows[0].host_id,
      metaId: rows[0].meta_id,
      serviceId: rows[0].service_id
    };
    metaServiceSelectors[name] = selector;

    const serviceDescription = `meta_${selector.metaId}`;

    cy.waitUntil(
      () =>
        cy
          .execInContainer({
            command:
              buildSubmitCriticalMetaServiceResultCommand(serviceDescription),
            name: 'web'
          })
          .requestOnDatabase({
            database: 'centreon_storage',
            params: [serviceDescription],
            query: buildMetaServiceEventCountQuery()
          })
          .then(([countRows]) => countRows[0].count_events > 0),
      { interval: 5000, timeout: 60000 }
    );
  });
};

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given('two meta-services have generated events', () => {
  cy.visit(PAGES.configuration.metaServicesLegacy);
  cy.wait('@getTimeZone');

  Object.values(metaServiceNames).forEach((name) => {
    cy.addMetaService({ maxCheckAttempts: '1', name });
  });

  cy.applyPollerConfiguration();

  Object.values(metaServiceNames).forEach((name) => {
    generateMetaServiceEvent(name);
  });
});

Given(
  'a restricted user is granted access to the Event Logs page and to the first meta-service',
  () => {
    cy.applyAclProfile(exportUserAclProfile);
    loginAs('event-logs-export-user');
  }
);

Given(
  'a restricted user is granted access to the Resources Status page only',
  () => {
    cy.applyAclProfile(noAccessUserAclProfile);
    loginAs('event-logs-no-access-user');
  }
);

When(
  'the administrator exports the event logs of the {word} meta-service as CSV',
  (position: string) => {
    exportEventLogsAsCsv({
      selection: toExportSelection(getMetaServiceSelector(position))
    }).as('csvExport');
  }
);

When(
  'the restricted user exports the event logs of the {word} meta-service as CSV',
  (position: string) => {
    exportEventLogsAsCsv({
      selection: toExportSelection(getMetaServiceSelector(position))
    }).as('csvExport');
  }
);

When(
  'the administrator exports the event logs of the meta-service {string} as CSV',
  (identifier: string) => {
    const { hostId } = getMetaServiceSelector('first');

    exportEventLogsAsCsv({ selection: `MS_${hostId}_${identifier}` }).as(
      'csvExport'
    );
  }
);

When('the restricted user exports the event logs as CSV', () => {
  exportEventLogsAsCsv({}).as('csvExport');
});

When('the restricted user exports the engine logs as CSV', () => {
  exportEventLogsAsCsv({ engine: true }).as('csvExport');
});

Then(
  'the CSV file only contains the events of the {word} meta-service',
  (position: string) => {
    const { metaId } = getMetaServiceSelector(position);

    cy.get<Cypress.Response<string>>('@csvExport').then(({ body, status }) => {
      expect(status).to.eq(200);

      const rows = getCsvEventRows(body);
      expect(rows).to.not.be.empty;
      rows.forEach(([, , hostName, , serviceDescription]) => {
        expect(hostName).to.eq('_Module_Meta');
        expect(serviceDescription).to.eq(`meta_${metaId}`);
      });
    });
  }
);

Then('the CSV file contains no event', () => {
  cy.get<Cypress.Response<string>>('@csvExport').then(({ body, status }) => {
    expect(status).to.eq(200);
    expect(body).to.contain('Begin date');
    expect(getCsvEventRows(body)).to.be.empty;
  });
});

Then('the export is refused', () => {
  cy.get<Cypress.Response<string>>('@csvExport')
    .its('status')
    .should('eq', 403);
});

afterEach(() => {
  cy.stopContainers();
});
