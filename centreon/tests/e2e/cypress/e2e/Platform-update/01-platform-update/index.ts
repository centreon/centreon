import { Given } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkIfSystemUserRoot,
  installEnginStatusWidget,
  setUserAdminDefaultCredentials
} from '../common';

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_module&action=list'
  }).as('getCentreonModulesList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_administration_widget&action=listInstalled&page_limit=60&page=1'
  }).as('loadInstalledWidgets');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/internal.php?object=centreon_module&action=install&id=engine-status&type=widget'
  }).as('installExtensions');
  cy.intercept({
    method: 'GET',
    url: '/centreon/widgets/engine-status/index.php'
  }).as('getEngineStatusView');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_poller&action=list&page_limit=60&page=1'
  }).as('loadPollersList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_home_customview&action=listViews'
  }).as('getCustomViewsList');
});

Given('an admin user with valid non-default credentials', () => {
  setUserAdminDefaultCredentials();
});

Given('a system user root', () => {
  checkIfSystemUserRoot();
});

Given('a running platform in version_A with all extensions installed', () => {
  installEnginStatusWidget();

  cy.loginByTypeOfUser({
    jsonName: 'admin-with-nondefault-credentials'
  });

  cy.navigateTo({
    page: 'Manager',
    rootItemNumber: 4,
    subMenu: 'Extensions'
  });

  cy.wait('@getCentreonModulesList');

  cy.get('button').eq(8).contains('Install all').click();

  cy.wait('@installExtensions').then(() => {
    cy.get('.SnackbarContent-root > .MuiPaper-root')
      .contains('Successful Installation')
      .should('have.length', 1);
  });
});

Given(
  'this platform has existing configuration for all the installed extensions',
  () => {
    cy.loginByTypeOfUser({
      jsonName: 'admin'
    });

    cy.navigateTo({
      page: 'Custom Views',
      rootItemNumber: 0
    });

    cy.wait('@getCustomViewsList');

    cy.getIframeBody().find('div[class="toggleEdit"]').eq(0).click();

    cy.getIframeBody().find('.cntn > .addView').eq(0).click();

    cy.getIframeBody()
      .find('input[name="name"]')
      .eq(0)
      .clear()
      .type('test view');

    cy.getIframeBody().find('input[name="submit"]').eq(0).click();

    cy.getIframeBody().find('.cntn > .addWidget').eq(0).click();

    cy.getIframeBody()
      .find('input[name="widget_title"]')
      .eq(0)
      .clear()
      .type('Engine status');

    cy.getIframeBody()
      .find('[name="formAddWidget"] #select2-widget_model_id-container')
      .eq(0)
      .click({ force: true });

    cy.wait('@loadInstalledWidgets').then(() => {
      cy.getIframeBody().find('[title="Engine-status"]').eq(0).click();

      cy.getIframeBody()
        .find('[name="formAddWidget"] [name="submit"]')
        .eq(0)
        .click();
    });

    cy.wait('@getEngineStatusView').then(() => {
      cy.getIframeBody().find('.ui-icon.ui-icon-wrench').eq(0).click();
    });

    cy.getIframeBody()
      .find('[name="Form"] .select2-selection__rendered')
      .eq(0)
      .click({ force: true });

    cy.wait('@loadPollersList').then(() => {
      cy.getIframeBody().find('[title="Central"]').eq(0).click();

      cy.getIframeBody()
        .find('[name="Form"] [name="param_2"]')
        .eq(0)
        .clear()
        .type('1');

      cy.getIframeBody().find('[name="Form"] [name="submit"]').eq(0).click();
    });
  }
);
