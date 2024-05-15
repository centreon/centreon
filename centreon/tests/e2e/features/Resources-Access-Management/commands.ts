import { CopyToContainerContentType } from '@centreon/js-config/cypress/e2e/commands';

Cypress.Commands.add(
  'createMultipleResourceAccessRules',
  (numberOfTimes, major_version) => {
    for (let i = 1; i <= numberOfTimes; i++) {
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
    command: `sed -i 's/"resource_access_management": 2,/"resource_access_management": 3,/g' /usr/share/centreon/config/features.json`,
    name: 'web'
  });
});

// the rpm package is taken from JFrog artifactroy repos
Cypress.Commands.add('installCloudExtensionsOnContainer', () => {
  return cy
    .copyToContainer({
      destination: `/tmp/`,
      source:
        './centreon-cloud-extensions-24.04.0-1712841285.82a1bda.el9.noarch.rpm',
      type: CopyToContainerContentType.File
    })
    .execInContainer({
      command: `ls tmp`,
      name: 'web'
    })
    .execInContainer({
      command: `dnf install /tmp/centreon-cloud-extensions-24.04.0-1712841285.82a1bda.el9.noarch.rpm`,
      name: 'web'
    })
    .execInContainer({
      command: `dnf install centreon-anomaly-detection`,
      name: 'web'
    });
});

Cypress.Commands.add('installCloudExtensionsModule', () => {
  cy.loginAsAdminViaApiV2();
  cy.visit(`/centreon/administration/extensions/manager`);
  cy.contains('.MuiCard-root', 'Anomaly Detection').within(() => {
    cy.getWebVersion().then(({ major_version, minor_version }) => {
      // cy.get('button').contains(`${major_version}.${minor_version}`).click();
      cy.get('button').contains(`24.04.0`).click();
    });
  });
  cy.contains('.MuiCard-root', 'Cloud Extensions').within(() => {
    cy.getWebVersion().then(({ major_version, minor_version }) => {
      // cy.get("button").contains(`${major_version}.${minor_version}`).click();
      cy.get('button').contains(`24.04.0`).click();
    });
  });
  cy.wait(30000);
  cy.logoutViaAPI();
});

Cypress.Commands.add('createSimpleUser', (userInformation, hostInformation) => {
  cy.setUserTokenApiV1();
  // verify later on if this user have BA access
  cy.addContact({
    admin: userInformation.admin,
    email: userInformation.email,
    name: userInformation.login,
    password: userInformation.password
  });
  cy.loginByTypeOfUser({ jsonName: 'admin' });
  cy.addHost({
    activeCheckEnabled: false,
    address: hostInformation.adress,
    checkCommand: 'check_centreon_cpu',
    hostGroup: hostInformation.hostGroups.hostGroup1.name,
    name: hostInformation.hosts.host1.name,
    template: 'generic-host'
  });
  cy.applyPollerConfiguration();
  cy.navigateTo({
    page: 'Contacts / Users',
    rootItemNumber: 3,
    subMenu: 'Users'
  });
  cy.getIframeBody().contains(userInformation.login).click();
  cy.wait('@getContactFrame');
  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .find('span[aria-labelledby$="-timeperiod_tp_id-container"]')
    .click();
  cy.getIframeBody().contains('24x7').click();
  cy.getIframeBody()
    .find('input[placeholder="Host Notification Commands"]')
    .parent()
    .parent()
    .click();
  cy.getIframeBody().contains('host-notify-by-email').click();
  cy.getIframeBody()
    .find('span[aria-labelledby$="-timeperiod_tp_id2-container"]')
    .click();
  cy.getIframeBody().contains('none').click();
  cy.getIframeBody()
    .find('input[placeholder="Service Notification Commands"]')
    .parent()
    .parent()
    .click();
  cy.getIframeBody().contains('host-notify-by-epager').click();

  cy.getIframeBody().find('li.b#c2').click();
  cy.getIframeBody().contains('label[for="reach_api_yes"]', 'Yes').click();
  cy.getIframeBody().contains('label[for="reach_api_rt_yes"]', 'Yes').click();
  cy.getIframeBody()
    .find('input[placeholder="Access list groups"]')
    .parent()
    .parent()
    .click();
  cy.getIframeBody().contains('customer_user_acl').click();
  cy.getIframeBody()
    .find('div#validForm')
    .find('.btc.bt_success[name="submitC"]')
    .click();
  cy.wait(3000);
});

Cypress.Commands.add('reloadAcl', () => {
  return cy.execInContainer({
    command: `sudo -u apache php /usr/share/centreon/cron/centAcl.php`,
    name: 'web'
  });
});

declare global {
  namespace Cypress {
    interface Chainable {
      createMultipleResourceAccessRules: () => Cypress.Chainable;
      enableResourcesAccessManagementFeature: () => Cypress.Chainable;
      installCloudExtensionsModule: () => Cypress.Chainable;
      installCloudExtensionsOnContainer: () => Cypress.Chainable;
      createSimpleUser: () => Cypress.Chainable;
      reloadAcl: () => Cypress.Chainable;
    }
  }
}
export {};
