import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { applyConfigurationViaClapi } from '../../../commons';
import {
  apiV2Base,
  buildFilesContainingCommand,
  buildHostIdQuery,
  encryptedValuePrefix,
  generatedConfigurationDirectories,
  generatedHostsFile,
  hostMacroValue,
  maskedSecretValue
} from '../common';

interface HostMacro {
  is_password: boolean;
  name: string;
  value: string | null;
}

const passwordMacroName = 'RBTSECRET';

let hostName = '';
let hostId = 0;
let snmpCommunity = '';
let macroSecret = '';
let hostMacros: Array<HostMacro> = [];

beforeEach(() => {
  cy.startContainers();

  hostName = '';
  hostId = 0;
  hostMacros = [];
  // Unique values, so that finding them anywhere proves a leak
  snmpCommunity = `rbt-snmp-${Date.now()}`;
  macroSecret = `rbt-macro-${Date.now()}`;

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

Given(
  'the host {word} has an SNMP community and a custom macro flagged as password',
  (host: string) => {
    hostName = host;

    clapi('ADD', 'HOST', `${host};${host};127.0.0.1;generic-host;central;`);
    clapi('SETPARAM', 'HOST', `${host};snmp_community;${snmpCommunity}`);
    clapi(
      'SETMACRO',
      'HOST',
      `${host};${passwordMacroName};${macroSecret};1;rbt password macro`
    );

    cy.requestOnDatabase({
      database: 'centreon',
      query: buildHostIdQuery(host)
    }).then(([rows]) => {
      expect(rows, `host ${host}`).to.have.length(1);

      hostId = Number(rows[0].host_id);
    });
  }
);

When('the host is reopened in the legacy host form', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });

  cy.then(() => {
    cy.visit(`${PAGES.configuration.hostsLegacy}&o=c&host_id=${hostId}`);
  });
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  cy.getIframeBody()
    .find('input[name="host_name"]')
    .should('have.value', hostName);
});

Then('both values are masked and absent from the page source', () => {
  cy.getIframeBody()
    .find('input[name="host_snmp_community"]')
    .should('have.value', maskedSecretValue);

  cy.getIframeBody()
    .find('input[name^="macroValue"]')
    .filter(
      (_, input) => (input as HTMLInputElement).value === maskedSecretValue
    )
    .should('have.length', 1);

  cy.getIframeBody().then((body) => {
    const source = body[0].ownerDocument.documentElement.outerHTML;

    expect(source).to.not.contain(snmpCommunity);
    expect(source).to.not.contain(macroSecret);
  });
});

When('the host is read through the API v2 configuration endpoint', () => {
  cy.loginAsAdminViaApiV2();

  cy.then(() =>
    cy
      .request({
        method: 'GET',
        url: `${apiV2Base}/configuration/hosts/${hostId}`
      })
      .then(({ body }) => {
        hostMacros = body.macros as Array<HostMacro>;
      })
  );
});

Then('the password macro value is null', () => {
  const macro = hostMacros.find(({ name }) => name === passwordMacroName);

  expect(macro, `macro ${passwordMacroName}`).to.not.eq(undefined);
  expect(macro?.is_password).to.eq(true);
  expect(macro?.value).to.eq(null);
});

When('the configuration is generated', () => {
  applyConfigurationViaClapi();
});

Then(
  'the generated files hold both secrets encrypted and never in clear text',
  () => {
    cy.execInContainer({
      command: buildFilesContainingCommand(generatedConfigurationDirectories, [
        snmpCommunity,
        macroSecret
      ]),
      name: 'web'
    }).then(({ output }) => {
      expect(output.trim(), 'files holding a secret in clear text').to.eq('');
    });

    cy.execInContainer({
      command: `cat ${generatedHostsFile}`,
      name: 'web'
    }).then(({ output }) => {
      ['SNMPCOMMUNITY', passwordMacroName].forEach((macroName) => {
        expect(
          hostMacroValue(output, hostName, macroName),
          `_${macroName} of ${hostName}`
        ).to.match(new RegExp(`^${encryptedValuePrefix}.+`));
      });
    });
  }
);
