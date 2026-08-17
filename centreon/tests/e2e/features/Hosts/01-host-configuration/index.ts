import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'commons';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  formSelectors,
  getListingRow,
  listingSelectors,
  searchInListing,
  waitForListingRefresh
} from '../common';

let hostName = '';
let hostWithGeoCoords = 'New-Host-Name-for-geo';
const hostAddress = '127.0.0.1';
const listingHosts = [
  { address: '10.0.0.1', name: 'host_alpha' },
  { address: '10.0.0.2', name: 'host_beta' },
  { address: '192.168.1.1', name: 'host_gamma' }
];
const hostGroupName = 'test_hg_filter';
const services = {
  serviceCritical: {
    host: 'host3',
    name: 'service3',
    template: 'SNMP-Linux-Load-Average'
  },
  serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' },
  serviceWarning: {
    host: 'host2',
    name: 'service2',
    template: 'SNMP-Linux-Memory'
  }
};
const resultsToSubmit = [
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceCritical.name,
    status: 'critical'
  },
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceWarning.name,
    status: 'warning'
  }
];

/**
 * Geographic coordinates live in the form's first section, which the modernized
 * form renders expanded — the legacy "Host Extended Infos" tab it used to sit
 * behind no longer exists.
 */
const fillGeographicCoordinates = (value: string) => {
  cy.getSidePanelBody().find('input[name="geo_coords"]').clear().type(value);
};

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
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.ajax.host_listing
  }).as('getHostListing');
  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.ajax.host_toggle
  }).as('toggleHost');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('a host is configured', () => {
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: services.serviceOk.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceOk.name,
      template: services.serviceOk.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceWarning.name,
      template: services.serviceWarning.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceCritical.name,
      template: services.serviceCritical.template
    })
    .applyPollerConfiguration();

  checkHostsAreMonitored([{ name: services.serviceOk.host }]);
  checkServicesAreMonitored([
    { name: services.serviceCritical.name },
    { name: services.serviceOk.name }
  ]);
  cy.submitResults(resultsToSubmit);
});

When('the admin changes the name of a host to {string}', (name: string) => {
  hostName = name;
  cy.openHostsListing();
  cy.openListingRowForm(services.serviceOk.host);
  cy.getSidePanelBody().find('input[name="host_name"]').clear().type(hostName);
  cy.getSidePanelBody().find('input[name="host_alias"]').clear().type(hostName);
  cy.getSidePanelBody().find(formSelectors.saveButton).first().click();
  cy.wait('@getTimeZone');
});

Then(
  'the updated name should be updated on the host page to {string}',
  (name: string) => {
    hostName = name;
    cy.exportConfig();
    cy.getIframeBody().contains(hostName).should('exist');
  }
);

When('the admin duplicates a host', () => {
  cy.openHostsListing();
  cy.runListingBulkAction(services.serviceOk.host, 'Duplicate', 'Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new host is created with identical fields', () => {
  cy.getIframeBody().contains(`${services.serviceOk.host}_1`).should('exist');
});

When('the admin deletes the host', () => {
  cy.openHostsListing();
  cy.runListingBulkAction(services.serviceOk.host, 'Delete', 'Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the host is not visible in the host list', () => {
  cy.getIframeBody().contains(services.serviceOk.host).should('not.exist');
});

Given('the admin is on the hosts listing page', () => {
  cy.openHostsListing();
});

Given('the admin fills in the required fields to create a host', () => {
  cy.getIframeBody().find('.cl-btn-add').click();
  cy.getSidePanelBody()
    .find('input[name="host_name"]', { timeout: 20_000 })
    .should('be.visible');
  cy.getSidePanelBody()
    .find('input[name="host_name"]')
    .clear()
    .type(hostWithGeoCoords);
  cy.getSidePanelBody()
    .find('input[name="host_alias"]')
    .clear()
    .type(hostWithGeoCoords);
  cy.getSidePanelBody()
    .find('input[name="host_address"]')
    .clear()
    .type(hostAddress);
});

When('the admin saves the host', () => {
  cy.getSidePanelBody().find(formSelectors.saveButton).first().click();
  cy.wait('@getTimeZone');
});

Then('the host is successfully created', () => {
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains(hostWithGeoCoords).should('be.visible');
});

Then('the geo-coordinates value is truncated {string}', (value: string) => {
  cy.openListingRowForm(hostWithGeoCoords);
  cy.getSidePanelBody()
    .find('input[name="geo_coords"]')
    .should('have.value', value);
});

Given(
  'the admin enters this non valid value {string} in the geo-coordinates field',
  (value: string) => {
    fillGeographicCoordinates(value);
  }
);

Given('a host is already configured', () => {
  hostWithGeoCoords = services.serviceOk.host;
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .contains('a', hostWithGeoCoords)
    .should('be.visible');
});

When('the admin opens the edit form on this host', () => {
  cy.openListingRowForm(hostWithGeoCoords);
});

// ---------------------------------------------------------------------------
// Modernized listing
// ---------------------------------------------------------------------------

Given('several hosts exist with different addresses', () => {
  listingHosts.forEach((host) => {
    cy.addHost({
      address: host.address,
      name: host.name,
      template: 'generic-host'
    });
  });
});

Given('the first host belongs to a dedicated hostgroup', () => {
  // addHostGroup() only creates the group; the membership goes through CLAPI.
  cy.addHostGroup({ alias: 'Test HG', name: hostGroupName });
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADDHOST',
      object: 'HG',
      values: `${hostGroupName};${listingHosts[0].name}`
    }
  });
});

When('the admin opens the hosts listing', () => {
  cy.openHostsListing();
});

Then('the AJAX listing table is displayed with the configured hosts', () => {
  cy.getIframeBody().find(listingSelectors.table).should('exist');
  listingHosts.forEach((host) => {
    cy.getIframeBody().find(listingSelectors.tableBody).contains(host.name);
  });
});

Then('each host row carries its address and poller', () => {
  getListingRow(listingHosts[0].name)
    .should('contain', listingHosts[0].address)
    .and('contain', 'Central');
});

When('the admin searches the hosts for {string}', (term: string) => {
  searchInListing(term, '@getHostListing');
});

Then('only the matching host is displayed in the hosts listing', () => {
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .contains(listingHosts[0].name);
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .should('not.contain', listingHosts[1].name)
    .and('not.contain', listingHosts[2].name);
});

Then('only the host carrying that address is displayed', () => {
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .contains(listingHosts[2].name);
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .should('not.contain', listingHosts[0].name);
});

When('the admin filters the listing on that hostgroup', () => {
  // The advanced filter box is collapsed until its toggle is clicked.
  cy.getIframeBody().find(listingSelectors.advancedToggle).click();
  cy.getIframeBody().find('#hostgroup').next('.select2-container').click();
  cy.getIframeBody()
    .find('.select2-results__option', { timeout: 20_000 })
    .contains(hostGroupName)
    .click({ force: true });
  cy.getIframeBody().find(listingSelectors.advancedSearch).click();
  waitForListingRefresh('@getHostListing');
});

Then('only the hosts of that hostgroup are displayed', () => {
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .contains(listingHosts[0].name);
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .should('not.contain', listingHosts[1].name);
});

When('the admin clears the advanced filters', () => {
  // Applying the filters dismisses the popover, so it has to be reopened to
  // reach its Clear button. One clear-all button resets hostgroup, poller and
  // template together — there is no per-filter clear control.
  cy.getIframeBody().find(listingSelectors.advancedToggle).click();
  cy.getIframeBody().find(listingSelectors.advancedClear).click();
  waitForListingRefresh('@getHostListing');
});

Then('all the hosts are displayed again', () => {
  listingHosts.forEach((host) => {
    cy.getIframeBody().find(listingSelectors.tableBody).contains(host.name);
  });
});

When('the admin toggles the first host off from the listing', () => {
  getListingRow(listingHosts[0].name)
    // The real checkbox is 0x0 behind the .cl-toggle slider; force the click.
    .find(listingSelectors.rowToggle)
    .should('be.checked')
    .click({ force: true });

  cy.wait('@toggleHost');
});

Then('the toggle request succeeds and the host is disabled', () => {
  cy.get('@toggleHost').its('response.statusCode').should('eq', 200);
  cy.get('@toggleHost')
    .its('response.body')
    .should('have.property', 'success', true);
  getListingRow(listingHosts[0].name)
    .find(listingSelectors.rowToggle)
    .should('not.be.checked');
});

When('the admin toggles the first host on from the listing', () => {
  getListingRow(listingHosts[0].name)
    .find(listingSelectors.rowToggle)
    .should('not.be.checked')
    .click({ force: true });

  cy.wait('@toggleHost').its('response.statusCode').should('eq', 200);
});

Then('the host is enabled again', () => {
  getListingRow(listingHosts[0].name)
    .find(listingSelectors.rowToggle)
    .should('be.checked');
});

Then(
  'every host row shows either a custom icon or the default host glyph',
  () => {
    // A host with its own (or an inherited) icon renders an <img> from
    // img/media; otherwise the row falls back to the inline HostIcon <svg>.
    cy.getIframeBody()
      .find(`${listingSelectors.tableBody} tr`)
      .each(($row) => {
        cy.wrap($row)
          .find('td')
          .eq(1)
          .find('img[src*="img/media"], svg')
          .should('have.length.greaterThan', 0);
      });
  }
);

Then(
  'the monitoring column shows a tooltipped badge or the not-monitored placeholder',
  () => {
    // These hosts are configured but never monitored, so centstorage has no
    // row for them and the column renders its "-" placeholder. Accept both so
    // the check holds whether or not the poller had time to report.
    getListingRow(listingHosts[0].name).then(($row) => {
      const badge = $row.find('.cl-mon-badge[data-cl-tooltip]');

      if (badge.length > 0) {
        expect(badge.attr('data-cl-tooltip')).to.contain('Status');

        return;
      }

      // Third cell (picker, name, monitoring): the placeholder is scoped to
      // that column, whereas a row-wide contain('-') is satisfied by the
      // 'generic-host' every row of this suite carries in Templates.
      expect($row.find('td').eq(2).text().trim()).to.equal('-');
    });
  }
);

Then(
  'the template of the first host opens the host template side panel',
  () => {
    // The link carries its target in data-panel-url, not href.
    getListingRow(listingHosts[0].name)
      .find('a[data-panel-url*="p=60103"]')
      .should('exist');
  }
);

Then('every host row links to its own services', () => {
  cy.getIframeBody()
    .find(`${listingSelectors.tableBody} tr`)
    .each(($row) => {
      cy.wrap($row).find('a[href*="p=602"]').should('exist');
    });
});

Then('the pagination information shows the total count of hosts', () => {
  cy.getIframeBody()
    .find(listingSelectors.pageInfo)
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

When('the admin sets the rows per page to 10', () => {
  cy.getIframeBody().find(listingSelectors.limitSelect).select('10');
  waitForListingRefresh('@getHostListing');
});

Then('at most 10 host rows are displayed', () => {
  cy.getIframeBody()
    .find(`${listingSelectors.tableBody} tr`)
    .should('have.length.at.most', 10);
});

When('the admin clicks the header checkbox', () => {
  cy.getIframeBody().find(listingSelectors.checkAll).click({ force: true });
});

Then('every host row checkbox is checked', () => {
  cy.getIframeBody()
    .find(`${listingSelectors.tableBody} ${listingSelectors.rowCheckbox}`)
    .each(($checkbox) => {
      cy.wrap($checkbox).should('be.checked');
    });
});

Then('every host row checkbox is unchecked', () => {
  cy.getIframeBody()
    .find(`${listingSelectors.tableBody} ${listingSelectors.rowCheckbox}`)
    .each(($checkbox) => {
      cy.wrap($checkbox).should('not.be.checked');
    });
});

Then('the listing issues a new AJAX request on its own', () => {
  // Auto-refresh is configured at 15s; the initial load is the first call.
  cy.waitUntil(
    () =>
      cy
        .get('@getHostListing.all')
        .then((calls) => (calls as unknown as Array<unknown>).length > 1),
    { interval: 2000, timeout: 30_000 }
  );
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .contains(listingHosts[0].name);
});

When('the admin opens the host form and comes back to the listing', () => {
  cy.openListingRowForm(listingHosts[0].name);
  cy.openHostsListing();
});

Then('the hosts search field still contains the search term', () => {
  cy.getIframeBody()
    .find(listingSelectors.searchInput)
    .should('have.value', listingHosts[0].name);
});
