import {
  apiBase,
  applyConfigurationViaClapi,
  getStatusNumberFromString,
  checkThatConfigurationIsExported,
  checkServicesAreMonitored,
  loginAsAdminViaApiV2,
  submitResultsViaClapi,
  versionApi,
  insertFixture,
  SubmitResult,
  updateFixturesResult
} from '../../commons';

interface Criteria {
  name: string;
  object_type: string | null;
  type: string;
  value: Array<{ id: string; name: string }>;
}

interface Filter {
  criterias: Array<Criteria>;
  name: string;
}

const serviceInAcknowledgementName = 'service_test_ack';
const serviceInDtName = 'service_test_dt';
const serviceOk = 'service_test_ok';
const secondServiceInDtName = 'service_test_dt_2';
const hostInAcknowledgementName = 'test_host';
const hostChildInAcknowledgementName = 'test_host_ack';
const stateFilterContainer = '[aria-label="State filter"]';
const searchInput = 'input[placeholder="Search"]';
const refreshButton = '[aria-label="Refresh"]';
const resourceMonitoringApi = /.+api\/beta\/monitoring\/resources.?page.+/;

const apiFilterResources = `${apiBase}/${versionApi}/users/filters/events-view`;

const initializeResourceData = (): Cypress.Chainable => {
  const files = [
    'resources/clapi/host1/01-add.json',
    'resources/clapi/service1/01-add.json',
    'resources/clapi/service1/02-set-max-check.json',
    'resources/clapi/service1/03-disable-active-check.json',
    'resources/clapi/service1/04-enable-passive-check.json',
    'resources/clapi/service2/01-add.json',
    'resources/clapi/service2/02-set-max-check.json',
    'resources/clapi/service2/03-disable-active-check.json',
    'resources/clapi/service2/04-enable-passive-check.json',
    'resources/clapi/service3/01-add.json',
    'resources/clapi/service3/02-set-max-check.json',
    'resources/clapi/service3/03-disable-active-check.json',
    'resources/clapi/service3/04-enable-passive-check.json',
    'resources/clapi/service4/01-add.json',
    'resources/clapi/service4/02-set-max-check.json',
    'resources/clapi/service4/03-disable-active-check.json',
    'resources/clapi/service4/04-enable-passive-check.json'
  ];

  return cy.wrap(Promise.all(files.map(insertFixture)));
};

const initializeAckChildRessources = (): Cypress.Chainable => {
  const files = [
    'resources/clapi/host3/01-add.json',
    'resources/clapi/host3/02-enable-passive-check.json',
    'resources/clapi/host3/03-disable-active-check.json',
    'resources/clapi/host3/04-set-max-check.json',
    'resources/clapi/host3/05-add-parent.json',
    'resources/clapi/host3/06-add-check-command.json',
    'resources/clapi/host3/07-enable-notification.json'
  ];

  return cy.wrap(Promise.all(files.map(insertFixture)));
};

const initializeAckRessources = (): Cypress.Chainable => {
  const files = [
    'resources/clapi/host1/01-add.json',
    'resources/clapi/host1/02-enable-passive-check.json',
    'resources/clapi/host1/03-disable-active-check.json',
    'resources/clapi/host1/04-set-max-check.json',
    'resources/clapi/host1/05-add-check-command.json',
    'resources/clapi/service1/01-add.json',
    'resources/clapi/service1/02-set-max-check.json',
    'resources/clapi/service1/03-disable-active-check.json',
    'resources/clapi/service1/04-enable-passive-check.json'
  ];

  return cy.wrap(Promise.all(files.map(insertFixture)));
};

const insertResourceFixtures = (): Cypress.Chainable => {
  const dateBeforeLogin = new Date();

  return updateFixturesResult().then((submitResults) => {
    loginAsAdminViaApiV2()
      .then(initializeResourceData)
      .then(applyConfigurationViaClapi)
      .then(() => checkThatConfigurationIsExported({ dateBeforeLogin }))
      .then(() =>
        checkServicesAreMonitored([{ name: serviceInAcknowledgementName }])
      )
      .then(() => submitResultsViaClapi(submitResults))
      .then(() =>
        checkServicesAreMonitored([
          { name: serviceInAcknowledgementName, output: 'submit_status' }
        ])
      );
  });
};

const insertDtResources = (): Cypress.Chainable => {
  const dateBeforeLogin = new Date();

  return cy
    .setUserTokenApiV1()
    .then(initializeResourceData)
    .then(applyConfigurationViaClapi)
    .then(() => checkThatConfigurationIsExported({ dateBeforeLogin }))
    .then(() => checkServicesAreMonitored([{ name: serviceInDtName }]));
};

const insertAckResourceFixtures = (): Cypress.Chainable => {
  const dateBeforeLogin = new Date();
  let results;
  updateFixturesResult().then((submitResult) => {
    results = submitResult;
  });

  return cy
    .setUserTokenApiV1()
    .then(initializeAckRessources)
    .then(applyConfigurationViaClapi)
    .then(initializeAckChildRessources)
    .then(applyConfigurationViaClapi)
    .then(() => checkThatConfigurationIsExported({ dateBeforeLogin }))
    .then(() =>
      checkServicesAreMonitored([{ name: serviceInAcknowledgementName }])
    )
    .then(() => submitResultsViaClapi(results))
    .refreshListing()
    .then(() => cy.contains(serviceInAcknowledgementName, { timeout: 30000 }))
    .then(() => cy.contains('submit_status_2', { timeout: 30000 }));
};

const setUserFilter = (body: Filter): Cypress.Chainable => {
  return cy
    .request({
      body,
      method: 'POST',
      url: apiFilterResources
    })
    .then((response) => {
      expect(response.status).to.eq(200);
      customFilterId = response.body.id;
    });
};

const deleteUserFilter = (): Cypress.Chainable => {
  if (customFilterId === null) {
    return cy.wrap({});
  }

  return cy
    .request({
      method: 'DELETE',
      url: `${apiFilterResources}/${customFilterId}`
    })
    .then((response) => {
      expect(response.status).to.eq(204);
      customFilterId = null;
    });
};

const tearDownResource = (): Cypress.Chainable => {
  const dateBeforeLogin = new Date();

  return cy
    .setUserTokenApiV1()
    .then(() => cy.removeResourceData())
    .then(applyConfigurationViaClapi)
    .then(() => checkThatConfigurationIsExported({ dateBeforeLogin }));
};

const tearDownAckResource = (): Cypress.Chainable => {
  return cy
    .setUserTokenApiV1()
    .executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'HOST',
        values: 'test_host_ack'
      }
    })
    .then(applyConfigurationViaClapi);
};

const checkIfUserNotificationsAreEnabled = (): void => {
  cy.log('Checking is user notifications are enabled.');

  const query = `SELECT contact_enable_notifications FROM contact WHERE contact_id = 1`;

  cy.requestOnDatabase({ database: 'centreon', query }).then(
    (value): Cypress.Chainable<null> | null => {
      const notificationAreEnabled = value === 1;

      if (notificationAreEnabled) {
        return null;
      }

      throw new Error(`User notifications are not enabled.`);
    }
  );
};

const submitCustomResultsViaClapi = (
  submitResults: SubmitResult
): Cypress.Chainable => {
  const timestampNow = Math.floor(Date.now() / 1000) - 15;

  return submitResultsViaClapi([
    {
      ...submitResults,
      status: getStatusNumberFromString(submitResults.status).toString(),
      updatetime: timestampNow.toString()
    }
  ]);
};

const clearCentengineLogs = (): Cypress.Chainable => {
  return cy.exec(
    `docker exec -i ${Cypress.env(
      'dockerName'
    )} truncate -s 0 /var/log/centreon-engine/centengine.log`
  );
};

const checkIfNotificationsAreNotBeingSent = (): void => {
  cy.log('Checking that if the notifications are being sent.');

  const logToSearch = '[notifications] [info]';

  cy.exec(
    `docker exec -i ${Cypress.env(
      'dockerName'
    )} sh -c "grep -iw '${logToSearch}' /var/log/centreon-engine/centengine.log | tail -1"`
  ).then(({ stdout }): Cypress.Chainable<null> | null => {
    if (!stdout) {
      return null;
    }

    throw new Error(`Notifications are being sent to contacts.`);
  });
};

const typeToSearchInput = (searchText: string): void => {
  cy.get(searchInput).as('searchInput');

  cy.get('@searchInput').clear();

  cy.get('@searchInput').type(searchText);

  cy.get('@searchInput').type('{esc}{enter}');
};

const actionBackgroundColors = {
  acknowledge: 'rgb(245, 241, 233)',
  inDowntime: 'rgb(240, 233, 248)',
  normal: 'rgba(0, 0, 0, 0)'
};
const actions = {
  acknowledge: 'Acknowledge',
  setDowntime: 'Set downtime'
};

let customFilterId = null;

export {
  stateFilterContainer,
  searchInput,
  refreshButton,
  resourceMonitoringApi,
  actionBackgroundColors,
  actions,
  serviceInAcknowledgementName,
  hostInAcknowledgementName,
  hostChildInAcknowledgementName,
  serviceInDtName,
  secondServiceInDtName,
  serviceOk,
  insertResourceFixtures,
  setUserFilter,
  deleteUserFilter,
  tearDownResource,
  checkIfUserNotificationsAreEnabled,
  insertAckResourceFixtures,
  submitCustomResultsViaClapi,
  checkIfNotificationsAreNotBeingSent,
  clearCentengineLogs,
  tearDownAckResource,
  typeToSearchInput,
  initializeResourceData,
  insertDtResources
};
