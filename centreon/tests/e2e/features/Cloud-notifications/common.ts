const enableNotificationFeature = (): Cypress.Chainable => {
  return cy.execInContainer({
    command: `sed -i 's@"notification" : 2@"notification" : 3@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
};

const createNotification = (body): Cypress.Chainable => {
  return cy
    .request({
      method: 'POST',
      url: 'centreon/api/latest/configuration/notifications',
      body: body
    })
    .then((response) => {
      cy.wrap(response);
    });
};

const editNotification = (body): Cypress.Chainable => {
  return cy
    .request({
      method: 'PUT',
      url: 'centreon/api/latest/configuration/notifications/1',
      body: body
    })
    .then((response) => {
      cy.wrap(response);
    });
};

export { enableNotificationFeature, createNotification, editNotification };
