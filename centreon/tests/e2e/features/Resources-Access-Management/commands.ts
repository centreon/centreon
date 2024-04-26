/* eslint-disable @typescript-eslint/no-namespace */
Cypress.Commands.add("executePostRequestMultipleTimes", () => {
  // eslint-disable-next-line no-plusplus
  for (let i = 1; i <= 15; i++) {
    const name = `Rule${i}`;
    const payload = {
      contact_groups: { all: false, ids: [] },
      contacts: { all: false, ids: [17] },
      dataset_filters: [
        { dataset_filter: null, resources: [14], type: "host" },
      ],
      description: "",
      is_enabled: true,
      name: name,
    };
    cy.request({
      body: payload,
      method: "POST",
      url: "/centreon/api/v24.04/administration/resource-access/rules?*",
    });
  }
});

declare global {
  namespace Cypress {
    interface Chainable {
      executePostRequestMultipleTimes: () => Cypress.Chainable;
    }
  }
}
export {};
