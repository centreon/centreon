import { PAGES } from 'fixtures/shared/constants/pages';

const trapsSnmpConfiguration = ({
  name,
  oid,
  vendor,
  output,
  string,
  regexp,
  severity
}): Cypress.Chainable => {
  cy.waitForElementInIframe('#main-content', 'input[name="traps_name"]');
  cy.getIframeBody().find('input[name="traps_name"]').type(name);
  cy.getIframeBody().find('input[name="traps_oid"]').type(oid);
  cy.getIframeBody()
    .find('span[id*="-manufacturer_id-container"]')
    .parent()
    .click();
  cy.getIframeBody().contains(`${vendor}`).click();
  cy.getIframeBody().find('input[name="traps_args"]').type(output);
  cy.getIframeBody().find('div#matchingrules_add').click();
  cy.getIframeBody().find('input#rule_0').clear().type(string);
  cy.getIframeBody().find('input#regexp_0').clear().type(regexp);
  return cy.getIframeBody().find('select#rulestatus_0').select(severity);
};

const UpdateTrapsSnmpConfiguration = ({
  name,
  oid,
  vendor,
  output,
  mode,
  status,
  behavior,
  string,
  regexp,
  severity,
  specialCommand,
  comments,
  serviceName,
  serviceTemplates,
  routingDefinition,
  filterServices,
  timeout,
  executionInterval,
  outputTransform,
  customCode
}): Cypress.Chainable => {
  cy.waitForElementInIframe('#main-content', 'input[name="traps_name"]');
  cy.getIframeBody().find('input[name="traps_name"]').clear().type(name);
  cy.getIframeBody().find('input[name="traps_oid"]').clear().type(oid);
  cy.getIframeBody()
    .find('span[id*="-manufacturer_id-container"]')
    .parent()
    .click();
  cy.getIframeBody().contains(`${vendor}`).click();
  cy.getIframeBody().find('input[name="traps_args"]').clear().type(output);
  cy.getIframeBody()
    .find(`input[name*="traps_mode"][value=${mode}]`)
    .parent()
    .click();
  cy.getIframeBody().find('select[name="traps_status"]').select(status);
  cy.getIframeBody()
    .find(`input[name*="traps_advanced_treatment"][value=${mode}]`)
    .parent()
    .click();
  cy.getIframeBody()
    .find('select[name="traps_advanced_treatment_default"]')
    .select(behavior);
  cy.getIframeBody().find('div#matchingrules_add').click();
  cy.getIframeBody().find('input#rule_0').clear().type(string);
  cy.getIframeBody().find('input#regexp_0').clear().type(regexp);
  cy.getIframeBody().find('select#rulestatus_0').select(severity);
  cy.getIframeBody()
    .find('input[name="traps_reschedule_svc_enable"]')
    .parent()
    .click();
  cy.getIframeBody()
    .find('input[name="traps_execution_command_enable"]')
    .parent()
    .click();
  cy.getIframeBody()
    .find('input[name="traps_execution_command"]')
    .type(specialCommand);
  cy.getIframeBody().find('textarea[name="traps_comments"]').type(comments);
  cy.getIframeBody().find('li#c2').click();
  cy.waitForElementInIframe('#main-content', 'span[name="services"]');
  cy.getIframeBody()
    .find('input[placeholder="Linked Services"]')
    .parent()
    .parent()
    .click();
  cy.getIframeBody().contains(serviceName).click();
  cy.getIframeBody()
    .find('input[placeholder="Linked Service Templates"]')
    .parent()
    .parent()
    .click();
  cy.getIframeBody().contains(serviceTemplates).click();
  cy.getIframeBody().find('li#c3').click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="traps_routing_value"]'
  );
  cy.getIframeBody().find('input[name="traps_routing_mode"]').parent().click();
  cy.getIframeBody()
    .find('input[name="traps_routing_value"]')
    .type(routingDefinition);
  cy.getIframeBody()
    .find('input[name="traps_routing_filter_services"]')
    .type(filterServices);
  cy.getIframeBody().find('input[name="traps_log"]').parent().click();
  cy.getIframeBody().find('input[name="traps_timeout"]').type(timeout);
  cy.getIframeBody()
    .find('input[name="traps_exec_interval"]')
    .type(executionInterval);
  cy.getIframeBody()
    .find('input[name*="traps_exec_interval_type"][value="2"]')
    .parent()
    .click();
  cy.getIframeBody()
    .find('input[name*="traps_downtime"][value="2"]')
    .parent()
    .click();
  cy.getIframeBody()
    .find('input[name="traps_output_transform"]')
    .type(outputTransform);
  cy.getIframeBody().find('textarea[name="traps_customcode"]').type(customCode);
  return cy.getIframeBody(); // Ensure a Cypress.Chainable is returned
};

function submitForm() {
  cy.getIframeBody()
    .find('div#validForm')
    .find('p.oreonbutton')
    .find('.btc.bt_success[name="submitA"]')
    .click();
}

const CreateOrUpdateTrapGroup = (body: TrapGroup): Cypress.Chainable => {
  cy.waitForElementInIframe('#main-content', 'input[name="name"]');
  cy.getIframeBody().find('input[name="name"]').clear().type(body.name);
  cy.getIframeBody().find('span[class="clearAllSelect2"]').click();
  cy.getIframeBody().find('input[class="select2-search__field"]').click();
  cy.wait('@listTraps');
  cy.getIframeBody().find(`div[title="${body.traps[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').click();
  cy.getIframeBody().find(`div[title="${body.traps[1]}"]`).click();
  return cy
    .getIframeBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(1)
    .click();
};

const AddOrUpdateVendor = (body: Vendor): Cypress.Chainable => {
  // wait for the name input to be charged on the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="name"]');
  // type a value on the Vendor Name input
  cy.getIframeBody().find('input[name="name"]').clear().type(body.name);
  // type a value on the Vendor Alias input
  cy.getIframeBody().find('input[name="alias"]').clear().type(body.alias);
  // type a value on the Vendor Description textarea
  cy.getIframeBody()
    .find('textarea[name="description"]')
    .clear()
    .type(body.description);
  // click on the first Save button
  const chain = cy
    .getIframeBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(0)
    .click();
  // export configuration
  cy.exportConfig();
  cy.wait('@getTimeZone');
  return chain;
};

const CheckVendorFieldsValues = (
  name: string,
  body: Vendor
): Cypress.Chainable => {
  // wait for the name input to be charged on the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="name"]');
  // check that the Vendor Name input contains the right value
  cy.getIframeBody().find('input[name="name"]').should('have.value', `${name}`);
  // check that the Vendor Alias input contains the right value
  cy.getIframeBody()
    .find('input[name="alias"]')
    .should('have.value', `${body.alias}`);
  // check that the Vendor Description textarea contains the right value
  return cy
    .getIframeBody()
    .find('textarea[name="description"]')
    .should('have.value', `${body.description}`);
};

interface TrapGroup {
  name: string;
  traps: Array<string>;
}

interface Vendor {
  name: string;
  alias: string;
  description: string;
}

// Trap reception on the Central (web container): snmptrapd receives the trap
// and hands it to centreontrapdforward, centreontrapd then processes it.
const trapdLogFile = '/var/log/centreon/centreontrapd.log';
const engineLogFile = '/var/log/centreon-engine/centengine.log';
const trapdSysconfigFile = '/etc/sysconfig/centreontrapd';

// Base of the OIDs of the traps created by the reception tests
const testTrapOidBase = '.1.3.6.1.4.1.99999.2148';

const trapdSignals = {
  Reload: 'RELOADCENTREONTRAPD',
  Restart: 'RESTARTCENTREONTRAPD'
};

// Value quoted for bash: everything is literal between single quotes
const shellQuote = (value: string): string =>
  `'${value.replace(/'/g, `'\\''`)}'`;

// snmptrap command sending a v2c trap whose varbinds are strings, numbered
// <trap OID>.1, <trap OID>.2...
const buildSendTrapCommand = ({
  destination = '127.0.0.1',
  oid,
  varbinds
}: SentTrap): string =>
  [
    `snmptrap -v 2c -c public ${destination}:162 ''`,
    oid,
    ...varbinds.map(
      (value, index) => `${oid}.${index + 1} s ${shellQuote(value)}`
    )
  ].join(' ');

// The default "error" severity hides the info lines centreontrapd writes for
// the PREEXEC and special commands it runs
const buildStartTrapDaemonsCommands = (): Array<string> => [
  `sed -i -E 's/--severity=[a-z]+/--severity=debug/' ${trapdSysconfigFile}`,
  'systemctl start snmptrapd',
  'systemctl restart centreontrapd'
];

const buildServiceOutputQuery = (): string =>
  'SELECT output, status FROM resources WHERE type = 0 AND enabled = 1 AND name = ? AND parent_name = ?';

// Resources holding an output, apart from one service (<=> also matches the
// NULL parent of the hosts)
const buildOtherResourcesWithOutputQuery = (): string =>
  'SELECT name, parent_name, output FROM resources WHERE enabled = 1 AND output LIKE ? AND NOT (name = ? AND parent_name <=> ?)';

const buildInsertPreexecQuery = (): string =>
  'INSERT INTO traps_preexec (trap_id, tpe_order, tpe_string) SELECT traps_id, 1, ? FROM traps WHERE traps_name = ?';

const startTrapDaemons = (): Cypress.Chainable => {
  cy.execInContainer({
    command: buildStartTrapDaemonsCommands(),
    name: 'web'
  });

  return cy.waitUntil(
    () =>
      cy
        .execInContainer({
          command: 'systemctl is-active snmptrapd centreontrapd || true',
          name: 'web'
        })
        .then(({ output }) =>
          output
            .trim()
            .split('\n')
            .every((state: string) => state.trim() === 'active')
        ),
    {
      errorMsg: 'snmptrapd and centreontrapd are not both active',
      interval: 2000,
      timeout: 60000
    }
  );
};

const sendTrap = (trap: SentTrap): Cypress.Chainable =>
  cy.execInContainer({ command: buildSendTrapCommand(trap), name: 'web' });

// Configuration > SNMP Traps > Generate, for the only poller of the platform
const generateTrapDatabase = ({
  apply,
  signal
}: TrapGeneration): Cypress.Chainable => {
  cy.visit(PAGES.configuration.snmpTrapsGenerateLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'select[name="host"]');
  cy.getIframeBody().find('input[name="generate"]').check({ force: true });
  if (apply) {
    cy.getIframeBody().find('input[name="apply"]').check({ force: true });
  }
  if (signal) {
    cy.getIframeBody().find('select[name="signal"]').select(signal);
  }
  cy.getIframeBody().find('input[value="Generate"]').click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', '#tab1 .ListTable');

  cy.getIframeBody()
    .find('#tab1 .ListTable')
    .should('contain', 'Poller (id:1): Sqlite database successfully created');
  if (apply) {
    cy.getIframeBody()
      .find('#tab1 .ListTable')
      .should('contain', 'Poller (id:1): SYNCTRAP sent to centcore.cmd');
  }
  if (signal) {
    cy.getIframeBody()
      .find('#tab1 .ListTable')
      .should(
        'contain',
        `Poller (id:1): ${trapdSignals[signal]} sent to centcore.cmd`
      );
  }

  return cy.wrap(null);
};

// Polls the output of a service in the resources table until it is the
// expected one; the output is returned for the following assertions
const waitForServiceOutput = ({
  host,
  matches,
  service
}: ServiceOutputCheck): Cypress.Chainable<string> => {
  let lastOutput = '';

  return cy
    .waitUntil(
      () =>
        cy
          .requestOnDatabase({
            database: 'centreon_storage',
            params: [service, host],
            query: buildServiceOutputQuery()
          })
          .then(([rows]) => {
            lastOutput = rows.length === 1 ? String(rows[0].output ?? '') : '';

            return matches(lastOutput.trim());
          }),
      {
        errorMsg: `Output of ${host} / ${service} is not the expected one`,
        interval: 2000,
        timeout: 120000
      }
    )
    .then(() => lastOutput.trim());
};

interface SentTrap {
  destination?: string;
  oid: string;
  varbinds: Array<string>;
}

interface TrapGeneration {
  apply: boolean;
  signal?: 'Reload' | 'Restart';
}

interface ServiceOutputCheck {
  host: string;
  matches: (output: string) => boolean;
  service: string;
}

export {
  AddOrUpdateVendor,
  buildInsertPreexecQuery,
  buildOtherResourcesWithOutputQuery,
  buildServiceOutputQuery,
  CheckVendorFieldsValues,
  CreateOrUpdateTrapGroup,
  engineLogFile,
  generateTrapDatabase,
  sendTrap,
  startTrapDaemons,
  submitForm,
  testTrapOidBase,
  trapdLogFile,
  trapsSnmpConfiguration,
  UpdateTrapsSnmpConfiguration,
  waitForServiceOutput
};
