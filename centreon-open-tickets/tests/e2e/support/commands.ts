/* eslint-disable @typescript-eslint/no-namespace */
import '@centreon/js-config/cypress/e2e/commands';
import '../features/commands';

Cypress.Commands.add('getBAMVersion', (): Cypress.Chainable => {
  return cy
    .exec(
      `bash -c "grep mod_release ../../www/modules/centreon-bam-server/conf.php | cut -d \\' -f 4"`
    )
    .then(({ stdout }) => {
      const found = stdout.match(/(\d+\.\d+)\.(\d+)/);
      if (found) {
        return cy.wrap({ major_version: found[1], minor_version: found[2] });
      }
      throw new Error('Current centreon BAM version cannot be parsed.');
    });
});

Cypress.Commands.add(
  'waitToHaveExpectedCountOfElements',
  (selector, expectedCount = 1, timeout = 50000, interval = 2000) => {
    cy.waitUntil(
      () => {
        return cy.get(selector).then(($elements) => {
          return cy.wrap($elements.length === expectedCount);
        });
      },
      {
        interval,
        timeout
      }
    );
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      getBAMVersion: () => Cypress.Chainable;
      waitToHaveExpectedCountOfElements(
        selector: string,
        expectedCount?: number,
        timeout?: number,
        interval?: number
      ): Cypress.Chainable;
    }
  }
}

export {};
