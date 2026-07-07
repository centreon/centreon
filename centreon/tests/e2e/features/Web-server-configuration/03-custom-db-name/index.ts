import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

const dbConfiguration = 'quality_centreon_config';
const dbStorage = 'quality_centreon_storage';

before(() => {
  cy.startContainers({ dbConfiguration, dbStorage });
});

after(() => {
  cy.stopContainers();
});

Given(
  'a platform whose databases are not named centreon / centreon_storage',
  () => {
    cy.intercept({
      method: 'GET',
      url: INTERCEPTORS.api.generate_reload_pollers
    }).as('generateAndReloadPollers');

    cy.loginByTypeOfUser({ jsonName: 'admin' });
  }
);

When('the administrator exports the central poller configuration', () => {
  cy.get('header').getByLabel({ label: 'Pollers', tag: 'button' }).click();
  cy.get('button[data-testid="Export configuration"]').click();
  cy.getByLabel({ label: 'Export & reload', tag: 'button' }).click();
});

Then('the configuration is generated and reloaded successfully', () => {
  cy.wait('@generateAndReloadPollers');
  cy.contains('Configuration exported and reloaded').should('exist');
});
