import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { INTERCEPTORS } from '../../../fixtures/shared/constants/interceptors';
import { PAGES } from '../../../fixtures/shared/constants/pages';
import {
  buildSetServiceAccountQuery,
  exportFileNamePattern,
  exportFolder,
  parseJsonBody,
  traversalFileName
} from '../common';

interface UserFixture {
  login: string;
  password: string;
}

const sessionLoginUrl =
  '/centreon/authentication/providers/configurations/local';

let exportFileName = '';
let probeFileName = '';
let lastResponse: Cypress.Response<unknown> | null = null;

beforeEach(() => {
  exportFileName = '';
  probeFileName = '';
  lastResponse = null;

  // loginByTypeOfUser waits on this alias, so register it up front.
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.awie.generateExport
  }).as('generateExport');
  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.awie.submitExport
  }).as('submitExport');

  // Use the dedicated AWIE image built by the dockerize job (not the slim
  // centreon-web base image, which is not published for this branch).
  cy.startContainers({ moduleName: 'centreon-awie', useSlim: false });
});

afterEach(() => {
  cy.stopContainers();
});

const listExportFolder = (): Cypress.Chainable<string> =>
  cy
    .execInContainer({ command: `ls ${exportFolder}`, name: 'web' })
    .then(({ output }) => cy.wrap(String(output), { log: false }));

// A fresh session for the user of the fixture, without going through the UI
const openSessionAs = (jsonName: string): void => {
  cy.clearCookies();
  cy.fixture(`users/${jsonName}.json`).then(
    ({ login, password }: UserFixture) =>
      cy
        .request({
          body: { login, password },
          log: false,
          method: 'POST',
          url: sessionLoginUrl
        })
        .its('status')
        .should('eq', 200)
  );
};

const createContact = ({
  admin,
  jsonName
}: {
  admin: boolean;
  jsonName: string;
}): void => {
  cy.fixture(`users/${jsonName}.json`).then(
    ({ login, password }: UserFixture) =>
      // GUI access is on by default
      cy.addContact({
        admin,
        email: `${login}@localhost`,
        name: login,
        password
      })
  );
};

// The download endpoint of the export, called the way the export page posts to it
const callExportEndpoint = ({
  fileName,
  withSession = true
}: {
  fileName: string;
  withSession?: boolean;
}): void => {
  if (!withSession) {
    cy.clearCookies();
  }

  cy.request({
    body: { pathFile: fileName },
    failOnStatusCode: false,
    form: true,
    method: 'POST',
    url: INTERCEPTORS.awie.submitExport
  }).then((response) => {
    lastResponse = response;
  });
};

Given('an admin user is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

When(
  'the user exports the contacts from Configuration > Import\\/Export > Export',
  () => {
    cy.visit(PAGES.awie.export);
    cy.waitForElementInIframe('#main-content', '#contact');
    cy.getIframeBody().find('#contact').check();
    cy.getIframeBody().find('.bt_success').click();

    cy.wait('@generateExport').then(({ response }) => {
      const { fileGenerate } = parseJsonBody(response?.body);

      expect(String(fileGenerate)).to.match(exportFileNamePattern);

      exportFileName = String(fileGenerate);
    });
  }
);

Then('a CLAPI export archive is downloaded', () => {
  cy.wait('@submitExport').then(({ response }) => {
    expect(response?.statusCode).to.eq(200);
    expect(String(response?.headers['content-type'])).to.contain(
      'application/zip'
    );
    expect(String(response?.headers['content-disposition'])).to.contain(
      `${exportFileName}.zip`
    );
  });

  listExportFolder().should('contain', `${exportFileName}.zip`);
});

When('the export endpoint is called without a session', () => {
  // A ready export text file: only the missing session can keep its archive
  // from being built
  probeFileName = `centreon-clapi-export-${Date.now()}`;
  cy.execInContainer({
    command: `touch ${exportFolder}/${probeFileName}.txt`,
    name: 'web'
  });

  callExportEndpoint({ fileName: probeFileName, withSession: false });
});

When(
  'the export endpoint is called without a session and with a path traversal in the file name',
  () => {
    callExportEndpoint({ fileName: traversalFileName, withSession: false });
  }
);

When(
  'the export endpoint is called by a non-admin user without import\\/export rights',
  () => {
    createContact({ admin: false, jsonName: 'rbt-awie-non-admin' });
    openSessionAs('rbt-awie-non-admin');

    // A real export file: the refusal can only come from the user's rights
    callExportEndpoint({ fileName: exportFileName });
  }
);

When('the export endpoint is called by an admin service account', () => {
  createContact({ admin: true, jsonName: 'rbt-awie-service-account' });
  cy.fixture('users/rbt-awie-service-account.json').then(
    ({ login }: UserFixture) =>
      cy.requestOnDatabase({
        database: 'centreon',
        query: buildSetServiceAccountQuery(login)
      })
  );
  openSessionAs('rbt-awie-service-account');

  callExportEndpoint({ fileName: exportFileName });
});

When(
  'the export endpoint is called by an admin with a path traversal in the file name',
  () => {
    openSessionAs('admin');

    callExportEndpoint({ fileName: traversalFileName });
  }
);

Then('the call is rejected with {string}', (message: string) => {
  expect(String(lastResponse?.headers['content-type'])).to.not.contain(
    'application/zip'
  );
  expect(parseJsonBody(lastResponse?.body).error).to.eq(message);
});

Then('no archive is produced', () => {
  listExportFolder().then((files) => {
    // The probe is there, so the listing is the right one
    expect(files).to.contain(`${probeFileName}.txt`);
    expect(files).to.not.contain(`${probeFileName}.zip`);
  });
});
