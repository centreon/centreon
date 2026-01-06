Cypress.Commands.add(
  'createMultipleResourceAccessRules',
  (numberOfTimes, majorVersion) => {
    for (let i = 1; i <= numberOfTimes; i += 1) {
      const name = `Rule${i}`;
      const payload = {
        // biome-ignore lint/style/useNamingConvention: false positive
        contact_groups: { all: false, ids: [] },
        contacts: { all: false, ids: [17] },
        // biome-ignore lint/style/useNamingConvention: false positive
        dataset_filters: [
          // biome-ignore lint/style/useNamingConvention: false positive
          { dataset_filter: null, resources: [14], type: 'host' }
        ],
        description: '',
        // biome-ignore lint/style/useNamingConvention: false positive
        is_enabled: true,
        name
      };
      cy.request({
        body: payload,
        method: 'POST',
        url: `/centreon/api/v${majorVersion}/administration/resource-access/rules?*`
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
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      createMultipleResourceAccessRules: (
        numberOfTimes,
        majorVersion
      ) => Cypress.Chainable;
      enableResourcesAccessManagementFeature: () => Cypress.Chainable;
    }
  }
}

export {};
