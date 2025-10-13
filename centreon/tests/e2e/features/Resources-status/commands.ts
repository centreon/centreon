Cypress.Commands.add('deleteAllEventViewFilters', () => {
  const baseUrl = '/centreon/api/latest/users/filters/events-view';
  cy.request({
    method: 'GET',
    url: `${baseUrl}?page=1&limit=100`
  }).then((response) => {
    expect(response.status).to.eq(200);

    const result = response.body.result;
    if (Array.isArray(result)) {
      const ids = result.map((item) => item.id);

      // Perform DELETE requests for each ID
      ids.forEach((id) => {
        cy.request({
          method: 'DELETE',
          url: `${baseUrl}/${id}`
        }).then((deleteResponse) => {
          expect(deleteResponse.status).to.eq(204);
          cy.log(`Deleted ID: ${id}`);
        });
      });
    } else {
      cy.log('No IDs found in the result.');
    }
  });
});

Cypress.Commands.add('setPassiveResource', (urlString) => {
  const payload = {
    // biome-ignore lint/style/useNamingConvention: <explanation>
    active_check_enabled: 0,
    // biome-ignore lint/style/useNamingConvention: <explanation>
    passive_check_enabled: 1
  };
  cy.request({
    body: payload,
    headers: {
      'Content-Type': 'application/json'
    },
    method: 'PATCH',
    url: urlString
  }).then((response) => {
    expect(response.status).to.eq(204);
  });
});

declare global {
  // biome-ignore lint/style/noNamespace: <explanation>
  namespace Cypress {
    interface Chainable {
      deleteAllEventViewFilters: () => Cypress.Chainable;
      setPassiveResource: (url: string) => Cypress.Chainable;
    }
  }
}

export {};
