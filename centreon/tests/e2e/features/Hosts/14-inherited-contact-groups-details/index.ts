import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  buildSetInheritanceModeQuery,
  cumulativeInheritanceMode
} from '../common';

const hostTemplates = ['rbt-cg-template-a', 'rbt-cg-template-b'];
const sharedContactGroup = 'rbt-cg-shared';
const ownContactGroup = 'rbt-cg-own';
// Notified by both templates too, so that the contacts list is not empty
const notifiedContact = 'rbt-cg-contact';
const hostName = 'rbt-cg-host';
const serviceName = 'rbt-cg-service';
const serviceTemplate = 'rbt-cg-service-template';

const contactGroupsLabel = 'Contact groups notified for this host';
const contactsLabel = 'Contacts notified for this service';
const contactGroupLinkSelector = 'a[href*="p=60302&o=c&cg_id="]';
const contactLinkSelector = 'a[href*="p=60301&o=c&contact_id="]';

beforeEach(() => {
  cy.startContainers();

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

// Text of the links of a list, each one trimmed
const linkTexts = (selector: string): Cypress.Chainable<Array<string>> =>
  cy
    .getIframeBody()
    .find(selector)
    .then((links) =>
      cy.wrap(
        links.toArray().map((link) => (link.textContent ?? '').trim()),
        { log: false }
      )
    );

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given('the deprecated monitoring pages are enabled', () => {
  clapi('SETPARAM', 'CONTACT', 'admin;show_deprecated_pages;1');

  // The preference is read when the session opens
  cy.clearCookies();
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given(
  'the contacts and contact groups calculation method is cumulative inheritance',
  () => {
    cy.requestOnDatabase({
      database: 'centreon',
      query: buildSetInheritanceModeQuery(cumulativeInheritanceMode)
    });
  }
);

Given('two host templates notifying the same contact group', () => {
  cy.addContact({
    admin: false,
    email: `${notifiedContact}@localhost`,
    name: notifiedContact,
    password: 'Centreon!2021RbtCg'
  });
  clapi('ADD', 'CG', `${sharedContactGroup};${sharedContactGroup}`);

  hostTemplates.forEach((template) => {
    clapi('ADD', 'HTPL', `${template};${template};;;;`);
    clapi('ADDCONTACTGROUP', 'HTPL', `${template};${sharedContactGroup}`);
    clapi('ADDCONTACT', 'HTPL', `${template};${notifiedContact}`);
  });
});

Given('a host using both templates and notifying another contact group', () => {
  clapi('ADD', 'CG', `${ownContactGroup};${ownContactGroup}`);

  cy.addHost({
    activeCheckEnabled: false,
    checkCommand: 'check_host_alive',
    name: hostName,
    template: hostTemplates.join('|')
  });
  clapi('ADDCONTACTGROUP', 'HOST', `${hostName};${ownContactGroup}`);

  // No contact on the service nor on its template (unlike generic-service):
  // the service uses the ones of its host
  clapi('ADD', 'STPL', `${serviceTemplate};${serviceTemplate};`);
  clapi(
    'SETPARAM',
    'STPL',
    `${serviceTemplate};check_command;check_centreon_dummy`
  );
  cy.addService({
    activeCheckEnabled: false,
    host: hostName,
    name: serviceName,
    template: serviceTemplate
  });
});

Given(
  'the host is monitored by a poller with the configuration exported',
  () => {
    cy.applyPollerConfiguration();
    checkHostsAreMonitored([{ name: hostName }]);
    checkServicesAreMonitored([{ name: serviceName }]);
  }
);

When(
  'the administrator opens the host in the legacy Status Details page',
  () => {
    cy.visit(
      `${PAGES.monitoring.statusDetailsHostsLegacy}&o=hd&host_name=${hostName}`
    );
    cy.wait('@getTimeZone');
  }
);

Then('the host details page is displayed', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `td:contains("${contactGroupsLabel}")`
  );
  cy.getIframeBody().contains(hostName).should('exist');
  cy.getIframeBody().contains(serviceName).should('exist');
});

Then('each notified contact group is listed once', () => {
  linkTexts(contactGroupLinkSelector).then((names) => {
    expect([...names].sort(), 'notified contact groups').to.deep.eq(
      [ownContactGroup, sharedContactGroup].sort()
    );
  });
});

When(
  "the administrator opens one of the host's services from that page",
  () => {
    cy.getIframeBody()
      .contains('a[href*="o=svcd"]', serviceName)
      .invoke('attr', 'href')
      .then((href) => {
        expect(href, 'link to the service details').to.contain(
          `service_description=${serviceName}`
        );

        // Follow the link in the main window, as the menu does
        cy.visit(`/centreon/${String(href).replace(/^\.\//, '')}`);
      });
    cy.wait('@getTimeZone');
  }
);

Then('the service details page is displayed', () => {
  cy.waitForElementInIframe('#main-content', `td:contains("${contactsLabel}")`);
  cy.getIframeBody().contains(serviceName).should('exist');
});

Then('the notified contacts are listed', () => {
  linkTexts(contactLinkSelector).then((names) => {
    expect(names, 'notified contacts').to.deep.eq([notifiedContact]);
  });
  linkTexts(contactGroupLinkSelector).then((names) => {
    expect([...names].sort(), 'notified contact groups').to.deep.eq(
      [ownContactGroup, sharedContactGroup].sort()
    );
  });
});
