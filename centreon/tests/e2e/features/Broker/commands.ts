import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../commons';
import {
  type BrokerStats,
  buildReadStatsCommand,
  buildSubmitResultsCommand,
  type CheckResult,
  parseSubmittedTimes
} from './common';

// APPLYCFG reloads Broker a few seconds after Engine: a result sent while
// its SQL output restarts never reaches the real-time tables
const brokerReloadWaitingTime = 15_000;

Cypress.Commands.add(
  'readBrokerStats',
  (statsFile: string): Cypress.Chainable<BrokerStats> =>
    cy
      .execInContainer(
        { command: buildReadStatsCommand(statsFile), name: 'web' },
        { log: false }
      )
      .then(({ output }) =>
        cy.wrap(JSON.parse(output) as BrokerStats, { log: false })
      )
);

Cypress.Commands.add(
  'submitResultsToEngine',
  (
    host: string,
    service: string,
    results: Array<CheckResult>
  ): Cypress.Chainable<Array<number>> =>
    cy
      .execInContainer({
        command: buildSubmitResultsCommand(host, service, results),
        name: 'web'
      })
      .then(({ output }) => {
        const times = parseSubmittedTimes(output);

        expect(
          times,
          'results written in the Engine command file'
        ).to.have.length(results.length);

        return cy.wrap(times, { log: false });
      })
);

// Passive host and service on the Central, exported and monitored
Cypress.Commands.add(
  'addExportedPassiveService',
  (host: string, service: string): Cypress.Chainable => {
    cy.addHost({
      activeCheckEnabled: false,
      address: '127.0.0.1',
      name: host,
      template: 'generic-host'
    });
    cy.addService({
      activeCheckEnabled: false,
      host,
      maxCheckAttempts: 1,
      name: service,
      template: 'Ping-LAN'
    });
    cy.applyPollerConfiguration();

    checkHostsAreMonitored([{ name: host }]);
    checkServicesAreMonitored([{ name: service }]);

    return cy.wait(brokerReloadWaitingTime);
  }
);

declare global {
  // biome-ignore lint/style/noNamespace: required for Cypress augmentation
  namespace Cypress {
    interface Chainable {
      addExportedPassiveService: (
        host: string,
        service: string
      ) => Cypress.Chainable;
      readBrokerStats: (statsFile: string) => Cypress.Chainable<BrokerStats>;
      submitResultsToEngine: (
        host: string,
        service: string,
        results: Array<CheckResult>
      ) => Cypress.Chainable<Array<number>>;
    }
  }
}
