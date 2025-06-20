import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import adminUser from "../../../fixtures/users/admin.json";
import restrictedUser from "../../../fixtures/users/restricted-user.json";
import data_acl_g from "../../../fixtures/acls/acl-access-group.json";
import data_acl_m from "../../../fixtures/acls/acl-access-menu.json";
import data_acl_r from "../../../fixtures/acls/acl-access-ressources.json";

import { checkHostsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';


const services = {
  serviceCritical: {
    host: "hostC",
    name: "service_test_critical",
    template: "SNMP-Linux-Load-Average",
  },
  serviceOk: { host: "hostO", name: "service_test_ok", template: "Ping-LAN" },
  serviceWarning: {
    host: "hostW",
    name: "service_test_warning",
    template: "SNMP-Linux-Memory",
  },
};
const resultsToSubmit = [
  {
    host: services.serviceWarning.host,
    output: "submit_status_2",
    service: services.serviceCritical.name,
    status: "critical",
  },
  {
    host: services.serviceWarning.host,
    output: "submit_status_2",
    service: services.serviceWarning.name,
    status: "warning",
  },
];

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: "GET",
    url: "/centreon/api/internal.php?object=centreon_topology&action=navigationList",
  }).as("getNavigationList");
  cy.intercept({
    method: "GET",
    url: "/centreon/include/common/userTimezone.php",
  }).as("getTimeZone");
});

Given("the admin user logs in", () => {
  cy.loginByTypeOfUser({
    jsonName: adminUser.login,
    loginViaApi: false,
  });
});

When("the admin user navigates to the Event Logs page", () => {
    cy.navigateTo({
      page: "Event Logs",
      rootItemNumber: 1,
      subMenu: "Event Logs",
    });
});

Then("the admin user should see all event logs", () => {
  cy.waitForElementInIframe("#main-content", 'select[id="host_group_filter"]');
  cy.getIframeBody()
    .find('select[id="host_group_filter"]')
    .siblings("span.select2-container")
    .click();

  cy.getIframeBody().find("button.btc.bt_info").contains("Select all").click();
  cy.getIframeBody().find("button.btc.bt_success").contains("Ok").click();
  // check event logs
  cy.getIframeBody()
    .find("table.ListTable tbody tr") // All table rows
    .find("td:nth-child(3) a") // get the 3rd column with object name with links to the host
    .then(($links) => {
      const found = [...$links].some(
        (link) => link.innerText.trim() === "Centreon-Server", // name of the host
      );
      expect(found).to.be.true;
    });


});

When("the admin creates an access group for the restricted user", () => {
  cy.setUserTokenApiV1();
  cy.addContact({
    admin: restrictedUser.admin,
    email: restrictedUser.email,
    name: restrictedUser.login,
    password: restrictedUser.password,
  });
  cy.navigateTo({
    page: "Access Groups",
    rootItemNumber: 4,
    subMenu: "ACL",
  });
  cy.wait("@getTimeZone");
  // Wait for the "Add" button to be available inside the iframe and click it
  cy.waitForElementInIframe("#main-content", 'a:contains("Add")');
  cy.getIframeBody().contains("a", "Add").click();

  cy.wait("@getTimeZone");
  cy.waitForElementInIframe("#main-content", 'input[name="acl_group_name"]');
  // Fill in the ACL group name field
  cy.getIframeBody().find('input[name="acl_group_name"]').type(data_acl_g.name);
  // Fill in the ACL group alias field
  cy.getIframeBody()
    .find('input[name="acl_group_alias"]')
    .type(data_acl_g.alias);
  // Select a user (from contacts) to be added to the ACL group
  cy.getIframeBody()
    .find('select[name="cg_contacts-f[]"]')
    .select(restrictedUser.login);
  // Click the button to move the selected user from "available" to "selected" list
  cy.getIframeBody()
    .find('input[name="add"]')
    .filter((index, el) => el.getAttribute("onclick")?.includes("cg_contacts"))
    .click();
  // Verify that the selected user now appears in the "selected" list
  cy.getIframeBody()
    .find('select[name="cg_contacts-t[]"]')
    .should("contain", restrictedUser.login);
  // Click the submit button to save the ACL group
  cy.getIframeBody().find('input[name="submitA"]').eq(0).click();
  cy.reload();
});

Then(
  "the admin grants the restricted user event Monitoring through the Menu Access ACL",
  () => {
  cy.navigateTo({
    page: "Menus Access",
    rootItemNumber: 4,
    subMenu: "ACL",
  });
  cy.wait("@getTimeZone");
  // Wait for the "Add" button to be available inside the iframe and click it
  cy.waitForElementInIframe("#main-content", 'a:contains("Add")');
  cy.getIframeBody().contains("a", "Add").click();

  cy.wait("@getTimeZone");
  // Wait for the ACL name input field to be available
  cy.waitForElementInIframe("#main-content", 'input[name="acl_topo_name"]');
  // Fill in the ACL menu access name field with the provided data
  cy.getIframeBody()
    .find('input[name="acl_topo_name"]')
    .type(data_acl_m.name);
  // Fill in the ACL menu access alias field with the provided data
  cy.getIframeBody()
    .find('input[name="acl_topo_alias"]')
    .type(data_acl_m.alias);
  // Select the ACL group from the available list
  cy.getIframeBody()
    .find('select[name="acl_groups-f[]"]')
    .select(data_acl_g.name);
  // Click the button to move the selected ACL group to the assigned list
  cy.getIframeBody()
    .find('input[name="add"]')
    .filter((index, el) => el.getAttribute("onclick")?.includes("acl_groups"))
    .click();
  // Enable specific menu access permissions by checking the corresponding checkboxes
  cy.getIframeBody()
    .find('input[name="acl_r_topos[2]"]') // Access to Monitoring Menu
    .check({ force: true });
  cy.getIframeBody().find('input[name="submitA"]').eq(0).click();
  cy.reload();
  },
);

When("the admin user logs out", () => {
  cy.logout();
});

Given("the restricted user logs in", () => {
  cy.loginByTypeOfUser({
    jsonName: "restricted-user",
    loginViaApi: false,
  });
});

When("the restricted user navigates to the Event Logs page", () => {
  cy.navigateTo({
    page: "Event Logs",
    rootItemNumber: 0,
    subMenu: "Event Logs",
  });
});

Then(
  "the event log page is accessible and restricted user should not see any event logs displayed",
  () => {
    cy.waitForElementInIframe(
      "#main-content",
      'select[id="host_group_filter"]',
    );
    cy.getIframeBody()
      .find('select[id="host_group_filter"]')
      .siblings("span.select2-container")
      .click();

    // No resource is available to be selected
    cy.getIframeBody()
      .find(
        "div.select2-results-header__nb-elements span.select2-results-header__nb-elements-value",
      )
      .should("have.text", "0");
    cy.getIframeBody()
      .find("button.btc.bt_info")
      .contains("Select all")
      .click();
    cy.getIframeBody().find("button.btc.bt_success").contains("Ok").click();
    // check event logs
    cy.getIframeBody()
      .find("table.ListTable tbody tr") // All table rows
      .find("td:nth-child(3)") // get the 3rd column with object name with no links to any host
      .then(($links) => {
        const found = [...$links].some(
          (link) => link.innerText.trim() === "Centreon-Server", // name of the host
        );
        expect(found).to.be.false;
      });
  },
);

When("the admin creates host resources", () => {
  cy.setUserTokenApiV1();
  cy.addHost({
    hostGroup: "Linux-Servers",
    name: services.serviceOk.host,
    template: "generic-host",
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceOk.name,
      template: services.serviceOk.template,
    })
  cy.addHost({
    hostGroup: "Linux-Servers",
    name: services.serviceWarning.host,
    template: "generic-host",
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceWarning.host,
      maxCheckAttempts: 1,
      name: services.serviceWarning.name,
      template: services.serviceWarning.template,
    })
  cy.addHost({
    hostGroup: "Linux-Servers",
    name: services.serviceCritical.host,
    template: "generic-host",
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceCritical.host,
      maxCheckAttempts: 1,
      name: services.serviceCritical.name,
      template: services.serviceCritical.template,
    })
    .applyPollerConfiguration();
  checkHostsAreMonitored([{ name: services.serviceOk.host },
                          { name: services.serviceCritical.host },
                          { name: services.serviceWarning.host }]);
  checkServicesAreMonitored([
    { name: services.serviceCritical.name },
    { name: services.serviceOk.name },
    { name: services.serviceWarning.name },
  ]);
  cy.submitResults(resultsToSubmit);
});


Then(
  "the admin assigns specific resources to the restricted user via Resource Access ACL",
  () => {
    cy.navigateTo({
      page: "Resources Access",
      rootItemNumber: 4,
      subMenu: "ACL",
    });
    cy.wait("@getTimeZone");
    // Wait for the "Add" button to be available inside the iframe and click it
    cy.waitForElementInIframe("#main-content", 'a:contains("Add")');
    cy.getIframeBody().contains("a", "Add").click();

    cy.wait("@getTimeZone");
    // Wait for the ACL name input field to be available
    cy.waitForElementInIframe("#main-content", 'input[name="acl_res_name"]');
    // Fill in the ACL menu access name field with the provided data
    cy.getIframeBody().find('input[name="acl_res_name"]').type(data_acl_r.name);
    // Fill in the ACL menu access alias field with the provided data
    cy.getIframeBody()
      .find('input[name="acl_res_alias"]')
      .type(data_acl_r.alias);
    // Select the ACL group from the available list
    cy.getIframeBody()
      .find('select[name="acl_groups-f[]"]')
      .select(data_acl_g.name);
    // Click the button to move the selected ACL group to the assigned list
    cy.getIframeBody()
      .find('input[name="add"]')
      .filter((index, el) => el.getAttribute("onclick")?.includes("acl_groups"))
      .click();
    // Navigates to Host resources section to select specific hosts
    cy.getIframeBody().find("#c2 a").click();
    cy.waitForElementInIframe(
      "#main-content",
      'h4:contains("Shared Resources")',
    );
    // Select hosts
    cy.getIframeBody()
      .find("select#acl_hosts-f")
      .select([
        services.serviceOk.host,
        services.serviceWarning.host,
        services.serviceCritical.host,
      ]);
    // Click the button to move the selected ACL group to the assigned list
    cy.getIframeBody()
      .find('input[name="add"]')
      .filter((index, el) => el.getAttribute("onclick")?.includes("acl_hosts"))
      .click();
    // Select all host groups
    cy.getIframeBody().find("#all_hostgroups").click();

    // Submit changes
    cy.getIframeBody().find('input[name="submitA"]').eq(0).click();
    cy.reload();
    cy.applyAcl();
  },
);

Then(
  "the restricted user should see only the event logs related to the assigned resources",
  () => {
    cy.waitForElementInIframe("#main-content", 'select[id="host_filter"]');
    cy.getIframeBody()
      .find('select[id="host_filter"]')
      .siblings("span.select2-container")
      .click();

    cy.getIframeBody()
      .find("button.btc.bt_info")
      .contains("Select all")
      .click();
    cy.getIframeBody().find("button.btc.bt_success").contains("Ok").click();
    // check event logs
    const expectedHosts = [
      services.serviceWarning.host,
      services.serviceCritical.host,
    ];

    cy.getIframeBody()
      .find("table.ListTable tbody tr td:nth-child(3) a")
      .then(($links) => {
        const hostNames = [...$links].map((link) => link.innerText.trim());

        expectedHosts.forEach((expectedHost) => {
          expect(hostNames).to.include(expectedHost);
        });
      });

  },
);

afterEach(() => {
  cy.stopContainers();
});