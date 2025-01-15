/* eslint-disable @typescript-eslint/no-namespace */
import '@centreon/js-config/cypress/e2e/commands';
import '../features/Dashboards/commands';

Cypress.Commands.add('enableDashboardFeature', () => {
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: 'web'
  });
});

Cypress.Commands.add('enablePlaylistFeature', () => {
  cy.execInContainer({
    command: `sed -i 's@"dashboard_playlist": 2@"dashboard_playlist": 3@' /usr/share/centreon/config/features.json`,
    name: 'web'
  });
});

export enum PatternType {
  contains = '*',
  endsWith = '$',
  equals = '',
  startsWith = '^'
}

declare global {
  namespace Cypress {
    interface Chainable {
      enableDashboardFeature: () => Cypress.Chainable;
      enablePlaylistFeature: () => Cypress.Chainable;
    }
  }
}
