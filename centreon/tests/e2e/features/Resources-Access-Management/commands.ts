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

Cypress.Commands.add('installBamModuleOnContainer', () => {
  return cy
    .execInContainer({
      command: `dnf install -y centreon-license-manager`,
      name: 'web'
    })
    .copyToContainer({
      destination: `/tmp/centreon-bam-server-24.05.0-1714994865.976635d.el9.noarch.rpm`,
      source:
        './fixtures/modules/centreon-bam-server-24.05.0-1714994865.976635d.el9.noarch.rpm',
      type: CopyToContainerContentType.File
    })
    .execInContainer({
      command: `dnf install -y /tmp/centreon-bam-server-24.05.0-1714994865.976635d.el9.noarch.rpm`,
      name: 'web'
    });
});

// the rpm package is taken from JFrog artifactroy repos
Cypress.Commands.add('installCloudExtensionsOnContainer', () => {
  return cy
    .copyToContainer({
      destination: `/tmp/centreon-cloud-extensions-24.04.0-1712841285.82a1bda.el9.noarch.rpm`,
      source:
        './fixtures/modules/centreon-cloud-extensions-24.04.0-1712841285.82a1bda.el9.noarch.rpm',
      type: CopyToContainerContentType.File
    })
    .execInContainer({
      command: `dnf install -y /tmp/centreon-cloud-extensions-24.04.0-1712841285.82a1bda.el9.noarch.rpm`,
      name: 'web'
    })
    .execInContainer({
      command: `dnf install -y centreon-anomaly-detection`,
      name: 'web'
    });
});

Cypress.Commands.add('installBamModule', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
  cy.visit(`/centreon/administration/extensions/manager`);
  cy.contains('.MuiCard-root', 'License Manager').within(() => {
    cy.getWebVersion().then(({ major_version, minor_version }) => {
      cy.get('button').contains(`${major_version}.${minor_version}`).click();
    });
  });
  cy.waitUntil(
    () => {
      return cy.get('[data-testid="PublishIcon"]').then(($element) => {
        cy.get('[data-testid="PublishIcon"]').click();

        return cy.wrap($element.length === 1);
      });
    },
    { interval: 3000, timeout: 8000 }
  );

  cy.get('input[type="file"]').attachFile({
    encoding: 'utf-8',
    filePath: '../../../../.github/scripts/license/bam.license',
    mimeType: 'application/octet-stream'
  });
  cy.get('[data-testid="Confirm"]').click();
  cy.contains('.MuiCard-root', 'Business Activity Monitoring').within(() => {
    cy.getWebVersion().then(({ major_version, minor_version }) => {
      cy.get('button').contains(`${major_version}.${minor_version}`).click();
    });
  });
});

Cypress.Commands.add('installCloudExtensionsModule', () => {
  cy.contains('.MuiCard-root', 'Anomaly Detection').within(() => {
    cy.getWebVersion().then(({ major_version, minor_version }) => {
      cy.get('button').contains(`${major_version}.${minor_version}`).click();
    });
  });

  cy.contains('.MuiCard-root', 'Anomaly Detection').within(() => {
    cy.get('[data-testid="CheckIcon"]').should('be.visible');
  });
  // cloud extensions is still under 24.04.0
  cy.contains('.MuiCard-root', 'Cloud Extensions').within(() => {
    // cy.getWebVersion().then(({ major_version, minor_version }) => {
    //   cy.get('button').contains(`${major_version}.${minor_version}`).click();
    // });
    cy.get('button').contains(`24.04.0`).click();
  });
  cy.contains('.MuiCard-root', 'Cloud Extensions').within(() => {
    cy.get('[data-testid="CheckIcon"]').should('be.visible');
  });
});

Cypress.Commands.add('addRightsForUser', (userInformation) => {
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
  cy.wait('@getTimeZone');
});

Cypress.Commands.add('grantBaAccessToUsers', () => {
  cy.navigateTo({
    page: 'Menus Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');
  cy.wait('@getKeepAlive');
  cy.getIframeBody().contains('customer_user_menu_access').click();
  cy.wait('@getTimeZone');
  cy.wait('@getTopCounteruser');
  cy.wait('@getTopCounterpoller');
  cy.wait('@getTopCounterservice');
  cy.wait('@getTopCounterhosts');
  cy.getIframeBody().find('img#img_1').click();
  cy.getIframeBody().find('input#i1_4').parent().click();
  cy.getIframeBody()
    .find('div#validForm')
    .find('.btc.bt_success[name="submitC"]')
    .click();
});

Cypress.Commands.add(
  'addBvsAndBas',
  (businessViewInfos, businessActivityInfos) => {
    businessViewInfos.forEach((value) => {
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'BV',
          values: `${value.Bv};${value.description}`
        }
      });
    });

    businessActivityInfos.forEach((value) => {
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'BA',
          values: `${value.Ba};${value.description};${value.State_Source};${value.Warning_threshold};${value.Critical_threshold};${value.Notification_interval}`
        }
      });
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'SETBV',
          object: 'BA',
          values: `${value.Ba};${value.Bv}`
        }
      });
    });
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      addBvsAndBas: () => Cypress.Chainable;
      addRightsForUser: () => Cypress.Chainable;
      createMultipleResourceAccessRules: () => Cypress.Chainable;
      enableResourcesAccessManagementFeature: () => Cypress.Chainable;
      grantBaAccessToUsers: () => Cypress.Chainable;
      installBamModule: () => Cypress.Chainable;
      installBamModuleOnContainer: () => Cypress.Chainable;
      installCloudExtensionsModule: () => Cypress.Chainable;
      installCloudExtensionsOnContainer: () => Cypress.Chainable;
      reloadAcl: () => Cypress.Chainable;
    }
  }
}
export {};
