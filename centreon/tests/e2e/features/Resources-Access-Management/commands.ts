/* eslint-disable @typescript-eslint/no-namespace */

Cypress.Commands.add(
  'createMultipleResourceAccessRules',
  (numberOfTimes, major_version) => {
    for (let i = 1; i <= numberOfTimes; i += 1) {
      const name = `Rule${i}`;
      const payload = {
        contact_groups: { all: false, ids: [] },
        contacts: { all: false, ids: [17] },
        dataset_filters: [
          { dataset_filter: null, resources: [14], type: 'host' }
        ],
        description: '',
        is_enabled: true,
        name
      };
      cy.request({
        body: payload,
        method: 'POST',
        url: `/centreon/api/v${major_version}/administration/resource-access/rules?*`
      });
    }
  }
);

Cypress.Commands.add('enableResourcesAccessManagementFeature', () => {
  return cy.execInContainer({
    command: `sed -i 's/"resource_access_management": [0-3]/"resource_access_management": 3/' /usr/share/centreon/config/features.json`,
    name: 'web'
  });
});

declare global {
  namespace Cypress {
    interface Chainable {
      createMultipleResourceAccessRules: (
        numberOfTimes,
        major_version
      ) => Cypress.Chainable;
      enableResourcesAccessManagementFeature: () => Cypress.Chainable;
    }
  }
}

export {};
