import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  buildHostIdByNameQuery,
  buildLockCommandQuery,
  buildServiceIdByNameQuery,
  cmaCommandNameMarker,
  cmaFreshnessThreshold
} from '../common';

type ConfigurationObject =
  | 'host template'
  | 'host'
  | 'service template'
  | 'service';

interface LegacyForm {
  // Text inputs to fill, by name, besides the check command
  fields: Record<string, string>;
  idParameter: 'host_id' | 'service_id';
  isTemplate: boolean;
  nameField: string;
  page: string;
  prefix: 'host' | 'service';
}

const objectNames: Record<ConfigurationObject, string> = {
  host: 'rbt-cma-host',
  'host template': 'rbt-cma-host-template',
  service: 'rbt-cma-service',
  'service template': 'rbt-cma-service-template'
};

const cmaCommand = `rbt-${cmaCommandNameMarker}-cpu`;
// Host the created service is linked to
const serviceHost = 'rbt-cma-service-host';

const legacyForms: Record<ConfigurationObject, LegacyForm> = {
  host: {
    fields: {
      host_address: '127.0.0.1',
      host_alias: objectNames.host,
      host_name: objectNames.host
    },
    idParameter: 'host_id',
    isTemplate: false,
    nameField: 'host_name',
    page: PAGES.configuration.hostsLegacy,
    prefix: 'host'
  },
  'host template': {
    fields: {
      host_alias: objectNames['host template'],
      host_name: objectNames['host template']
    },
    idParameter: 'host_id',
    isTemplate: true,
    nameField: 'host_name',
    page: PAGES.configuration.hostsTemplatesLegacy,
    prefix: 'host'
  },
  service: {
    fields: { service_description: objectNames.service },
    idParameter: 'service_id',
    isTemplate: false,
    nameField: 'service_description',
    page: PAGES.configuration.servicesByHostLegacy,
    prefix: 'service'
  },
  'service template': {
    fields: {
      service_alias: objectNames['service template'],
      service_description: objectNames['service template']
    },
    idParameter: 'service_id',
    isTemplate: true,
    nameField: 'service_description',
    page: PAGES.configuration.servicesTemplatesLegacy,
    prefix: 'service'
  }
};

const objectPattern = '(host template|host|service template|service)';

let currentForm: LegacyForm | null = null;

beforeEach(() => {
  cy.startContainers();

  currentForm = null;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

const form = (): LegacyForm => {
  if (currentForm === null) {
    throw new Error('No creation form opened');
  }

  return currentForm;
};

const selectInSelect2 = (containerSelector: string, value: string): void => {
  cy.getIframeBody().find(containerSelector).click();
  cy.getIframeBody()
    .find('.select2-container--open input.select2-search__field')
    .type(value);
  cy.getIframeBody().find(`div[title="${value}"]`).click();
};

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given('the Centreon Monitoring Agent connector is installed', () => {
  // The connector installs locked check commands named after the agent
  clapi(
    'ADD',
    'CMD',
    `${cmaCommand};check;$CENTREONPLUGINS$/centreon_linux_local.pl --plugin=os::linux::local::plugin --mode=cpu`
  );
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildLockCommandQuery(cmaCommand)
  });

  clapi('ADD', 'HOST', `${serviceHost};${serviceHost};127.0.0.1;;Central;`);
});

Given(
  new RegExp(`^the admin is on the ${objectPattern} creation form$`),
  (object: ConfigurationObject) => {
    currentForm = legacyForms[object];

    cy.visit(`${currentForm.page}&o=a`);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      `input[name="${currentForm.nameField}"]`
    );
  }
);

When(
  new RegExp(
    `^the admin creates a ${objectPattern} with a Centreon Monitoring Agent check command$`
  ),
  (object: ConfigurationObject) => {
    Object.entries(form().fields).forEach(([name, value]) => {
      cy.getIframeBody().find(`input[name="${name}"]`).clear().type(value);
    });

    if (object === 'service') {
      cy.getIframeBody().find('input[placeholder="Hosts"]').type(serviceHost);
      cy.getIframeBody().find(`div[title="${serviceHost}"]`).click();
    }

    selectInSelect2(
      'span[id="select2-command_command_id-container"]',
      cmaCommand
    );

    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
  }
);

When(
  new RegExp(`^the admin opens the ${objectPattern} again$`),
  (object: ConfigurationObject) => {
    const { idParameter, isTemplate, nameField, page, prefix } = form();
    const query =
      prefix === 'host'
        ? buildHostIdByNameQuery(objectNames[object], isTemplate)
        : buildServiceIdByNameQuery(objectNames[object], isTemplate);

    cy.requestOnDatabase({ database: 'centreon', query }).then(([rows]) => {
      expect(rows, `${object} ${objectNames[object]}`).to.have.length(1);

      cy.visit(`${page}&o=c&${idParameter}=${rows[0].id}`);
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', `input[name="${nameField}"]`);
    cy.getIframeBody()
      .find(`input[name="${nameField}"]`)
      .should('have.value', objectNames[object]);
  }
);

Then('freshness is enabled', () => {
  const { prefix } = form();

  cy.getIframeBody()
    .find(
      `input[name="${prefix}_check_freshness[${prefix}_check_freshness]"][value="1"]`
    )
    .should('be.checked');
});

Then('the freshness threshold is {int} seconds', (threshold: number) => {
  const { prefix } = form();

  expect(threshold).to.eq(cmaFreshnessThreshold);

  cy.getIframeBody()
    .find(`input[name="${prefix}_freshness_threshold"]`)
    .should('have.value', String(threshold));
});
