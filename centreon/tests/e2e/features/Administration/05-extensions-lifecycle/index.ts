import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  buildInstalledModuleQuery,
  buildWidgetCountQuery,
  buildWriteModuleCommand,
  duplicatedMenuEntriesQuery,
  duplicatedModulesQuery,
  duplicatedWidgetsQuery
} from '../common';

// Directory of the core widget "Live Top 10 Memory Usage" (www/widgets/)
const memoryWidgetDirectory = 'live-top10-memory-usage';

let moduleName = '';
// Version of the module files on the platform
let moduleFilesVersion = '';
let installedModuleVersion = '';
let baselineDuplicatedMenuEntries: Array<string> = [];

beforeEach(() => {
  cy.startContainers();

  moduleName = '';
  moduleFilesVersion = '';
  installedModuleVersion = '';
  baselineDuplicatedMenuEntries = [];

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.centreon_module}&action=list`
  }).as('getExtensions');
  cy.intercept({
    method: 'POST',
    url: `${INTERCEPTORS.api.centreon_module}&action=install`
  }).as('installExtension');
  cy.intercept({
    method: 'POST',
    url: `${INTERCEPTORS.api.centreon_module}&action=update`
  }).as('updateExtension');
  cy.intercept({
    method: 'DELETE',
    url: `${INTERCEPTORS.api.centreon_module}&action=remove`
  }).as('removeExtension');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.monitor_resources}?page`
  }).as('getResources');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

const moduleCard = (): Cypress.Chainable =>
  cy.get(`[id="module-${moduleName}"]`);

const openExtensionsManager = (): void => {
  cy.visit(PAGES.configuration.extensionsManager);
  cy.wait('@getExtensions');
};

const expectModuleRelease = (release: string | null): void => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildInstalledModuleQuery(moduleName)
  }).then(([rows]) => {
    if (release === null) {
      expect(rows, `${moduleName} in modules_informations`).to.have.length(0);

      return;
    }

    expect(rows, `${moduleName} in modules_informations`).to.have.length(1);
    expect(rows[0].mod_release).to.eq(release);
  });
};

const expectSuccessfulResponse = (alias: string): void => {
  cy.wait(alias).then(({ response }) => {
    expect(response?.statusCode, `${alias} status code`).to.eq(200);
    expect(response?.body.status, `${alias} status`).to.eq(true);
  });
};

Given('an admin user is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given(
  'a disposable module {string} in version {string} is present on the platform and not installed',
  (name: string, version: string) => {
    moduleName = name;
    moduleFilesVersion = version;

    cy.requestOnDatabase({
      database: 'centreon',
      query: duplicatedMenuEntriesQuery
    }).then(([rows]) => {
      baselineDuplicatedMenuEntries = rows.map(({ topology_page }) =>
        String(topology_page)
      );
    });

    cy.execInContainer({
      command: buildWriteModuleCommand(name, version),
      name: 'web'
    });

    expectModuleRelease(null);
  }
);

When(
  'the admin installs {string} from the Extensions manager',
  (name: string) => {
    openExtensionsManager();

    // A module that is not installed shows an install button labelled with
    // its available version
    cy.get(`[id="module-${name}"]`)
      .find('button')
      .should('contain', moduleFilesVersion)
      .click();

    expectSuccessfulResponse('@installExtension');
    cy.wait('@getExtensions');
  }
);

Then(
  'the module is listed as installed in version {string}',
  (version: string) => {
    moduleCard()
      .find('.MuiChip-root')
      .should('contain', version)
      .find('[data-testid="CheckIcon"]')
      .should('exist');

    expectModuleRelease(version);
    installedModuleVersion = version;
  }
);

When('the module files are upgraded to version {string}', (version: string) => {
  cy.execInContainer({
    command: buildWriteModuleCommand(moduleName, version),
    name: 'web'
  });
  moduleFilesVersion = version;
  // opcache revalidates the PHP files every 2 seconds by default
  cy.wait(3000);
});

When(
  'the admin updates {string} from the Extensions manager',
  (name: string) => {
    openExtensionsManager();

    // An installed module older than its files is outdated: its chip shows
    // the installed version and an update icon
    cy.get(`[id="module-${name}"]`)
      .find('.MuiChip-root')
      .should('contain', installedModuleVersion)
      .find('[data-testid="SystemUpdateAltIcon"]')
      .click();

    expectSuccessfulResponse('@updateExtension');
    cy.wait('@getExtensions');
  }
);

Then(
  'the module is listed as up to date in version {string}',
  (version: string) => {
    moduleCard()
      .find('.MuiChip-root')
      .should('contain', version)
      .find('[data-testid="CheckIcon"]')
      .should('exist');
    moduleCard()
      .find('[data-testid="SystemUpdateAltIcon"]')
      .should('not.exist');

    expectModuleRelease(version);
  }
);

When(
  'the admin deletes {string} from the Extensions manager',
  (name: string) => {
    cy.get(`[id="module-${name}"]`)
      .find('.MuiChip-root [data-testid="DeleteIcon"]')
      .click();

    cy.getByTestId({ testId: 'Confirm' }).click();

    expectSuccessfulResponse('@removeExtension');
    cy.wait('@getExtensions');
  }
);

Then('the module is listed as not installed', () => {
  moduleCard()
    .find('button')
    .should('contain', moduleFilesVersion)
    .find('[data-testid="AddIcon"]')
    .should('exist');
  moduleCard().find('.MuiChip-root').should('not.exist');

  expectModuleRelease(null);
});

When(
  'the admin opens the widget {string} in the Extensions manager',
  (title: string) => {
    openExtensionsManager();

    cy.get(`[id="widget-${memoryWidgetDirectory}"]`).should('contain', title);
  }
);

Then('the widget is installed once', () => {
  // An installed core widget shows a chip, not an install button
  cy.get(`[id="widget-${memoryWidgetDirectory}"]`)
    .find('.MuiChip-root')
    .should('exist');

  cy.requestOnDatabase({
    database: 'centreon',
    query: buildWidgetCountQuery(memoryWidgetDirectory)
  }).then(([rows]) => {
    expect(Number(rows[0].count), `${memoryWidgetDirectory} rows`).to.eq(1);
  });
});

Then('the widget offers no delete action', () => {
  cy.get(`[id="widget-${memoryWidgetDirectory}"]`)
    .find('.MuiChip-root')
    .should('exist')
    .find('[data-testid="DeleteIcon"]')
    .should('not.exist');
});

Then('no module or menu entry is duplicated', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: duplicatedModulesQuery
  }).then(([rows]) => {
    expect(rows, 'duplicated modules').to.have.length(0);
  });

  cy.requestOnDatabase({
    database: 'centreon',
    query: duplicatedWidgetsQuery
  }).then(([rows]) => {
    expect(rows, 'duplicated widgets').to.have.length(0);
  });

  cy.requestOnDatabase({
    database: 'centreon',
    query: duplicatedMenuEntriesQuery
  }).then(([rows]) => {
    expect(
      rows.map(({ topology_page }) => String(topology_page)),
      'menu pages declared more than once'
    ).to.deep.eq(baselineDuplicatedMenuEntries);
  });
});

Then('the Resources Status and Hosts pages still open without error', () => {
  cy.visit(PAGES.monitoring.resourcesStatus);
  cy.wait('@getResources').its('response.statusCode').should('eq', 200);
  cy.get('input[placeholder="Search"]').should('be.visible');

  cy.visit(PAGES.configuration.hostsLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="searchH"]');
  cy.getIframeBody().contains('Centreon-Server').should('be.visible');
});
