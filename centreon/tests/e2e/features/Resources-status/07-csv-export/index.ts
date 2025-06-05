/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from "@badeball/cypress-cucumber-preprocessor";

import {
  checkMetricsAreMonitored,
  checkServicesAreMonitored,
} from "../../../commons";

const serviceOk = "service_test_ok";
const serviceInDtName = "service_downtime_1";
const secondServiceInDtName = "service_downtime_2";
const serviceInAcknowledgementName = "service_ack_1";

const COLUMNS_TO_COMPARE = [
  "Status",
  "Parent Resource Name",
  "Parent Resource Status",
  "Parent Resource Type",
  "Parent alias",
  "Resource Name",
  "Resource Type",
];

const ALL_COLUMNS = [
  "Status",
  "Resource Type",
  "Resource Name",
  "Parent Resource Type",
  "Parent Resource Name",
  "Parent Resource Status",
  "Duration",
  "Last Check",
  "Information",
  "Tries",
  "Severity",
  "Notes",
  "Action",
  "State",
  "Alias",
  "Parent alias",
  "FQDN / Address",
  "Monitoring server",
  "Notif",
  "Check",
];

const UPDATED_COLUMNS = [
  "Status",
  "Resource Type",
  "Resource Name",
  "Parent Resource Type",
  "Parent Resource Name",
  "Parent Resource Status",
  "Last Check",
  "Information",
  "Notes",
  "Action",
  "FQDN / Address",
  "Notif",
  "Check",
];

const downloadsFolder = Cypress.config("downloadsFolder");

const normalize = (text: string) =>
  text.trim().replace(/^"|"$/g, "").replace(/\\"/g, "").replace(/"/g, "");

before(() => {
  cy.intercept({
    method: "POST",
    url: "/centreon/api/latest/authentication/providers/configurations/local",
  }).as("postLocalAuthentication");

  cy.intercept({
    method: "GET",
    url: "/centreon/api/internal.php?object=centreon_topology&action=navigationList",
  }).as("getNavigationList");

  cy.intercept({
    method: "GET",
    url: "/centreon/api/latest/users/filters/events-view?page=1&limit=100",
  }).as("getFilters");

  cy.intercept("/centreon/api/latest/monitoring/resources*").as(
    "monitoringEndpoint"
  );

  cy.startContainers();

  cy.loginByTypeOfUser({
    jsonName: "admin",
    loginViaApi: true,
  }).wait("@getFilters");

  cy.disableListingAutoRefresh();

  cy.addHost({
    activeCheckEnabled: false,
    checkCommand: "check_centreon_cpu",
    name: "host1",
    template: "generic-host",
  })
    .addService({
      activeCheckEnabled: false,
      host: "host1",
      maxCheckAttempts: 1,
      name: serviceInDtName,
      template: "SNMP-DISK-/",
    })
    .addService({
      activeCheckEnabled: false,
      host: "host1",
      maxCheckAttempts: 1,
      name: secondServiceInDtName,
      template: "Ping-LAN",
    })
    .addService({
      activeCheckEnabled: false,
      host: "host1",
      maxCheckAttempts: 1,
      name: serviceInAcknowledgementName,
      template: "SNMP-DISK-/",
    })
    .addService({
      activeCheckEnabled: false,
      host: "host1",
      maxCheckAttempts: 1,
      name: serviceOk,
      template: "Ping-LAN",
    })
    .applyPollerConfiguration();

  checkServicesAreMonitored([
    {
      name: serviceOk,
    },
  ]);

  cy.submitResults([
    {
      host: "host1",
      output: "submit_status_2",
      service: serviceInDtName,
      status: "critical",
    },
    {
      host: "host1",
      output: "submit_status_2",
      service: secondServiceInDtName,
      status: "critical",
    },
    {
      host: "host1",
      output: "submit_status_2",
      service: serviceInAcknowledgementName,
      status: "critical",
    },
    {
      host: "host1",
      output: "submit_status_0",
      service: serviceOk,
      status: "ok",
    },
  ]);

  checkServicesAreMonitored([
    {
      name: serviceInDtName,
      status: "critical",
    },
    {
      name: secondServiceInDtName,
      status: "critical",
    },
    {
      name: serviceInAcknowledgementName,
      status: "critical",
    },
    {
      name: serviceOk,
      status: "ok",
    },
  ]);

  ["Disk-/", "Load", "Memory", "Ping"].forEach((service) => {
    cy.scheduleServiceCheck({ host: "Centreon-Server", service });
  });

  checkMetricsAreMonitored([
    {
      host: "Centreon-Server",
      name: "rta",
      service: "Ping",
    },
  ]);
  cy.logoutViaAPI();
});

beforeEach(() => {
  cy.intercept({
    method: "GET",
    url: "/centreon/api/internal.php?object=centreon_topology&action=navigationList",
  }).as("getNavigationList");
  cy.intercept({
    method: "GET",
    url: "/centreon/include/common/userTimezone.php",
  }).as("getTimeZone");
  cy.intercept({
    method: "GET",
    url: "/centreon/api/latest/monitoring/resources?page=*",
  }).as("getResources");
  cy.intercept({
    method: "GET",
    url: "/centreon/api/latest/monitoring/resources/hosts/*",
  }).as("getResourcesDetails");
  cy.intercept("/centreon/api/latest/monitoring/resources*").as(
    "monitoringEndpoint"
  );
  cy.intercept("GET", "**/monitoring/resources/count*").as("getResourceCount");
});

after(() => {
  cy.stopContainers();
});

Given("an admin user is logged in and redirected to the Resource Status page", () => {
  cy.loginByTypeOfUser({
    jsonName: "admin",
    loginViaApi: false,
  });
});

When("the admin user clicks the Export button", () => {
  cy.task("clearDownloadsFolder", { downloadsFolder }).then(() => {
    cy.log("Downloads folder cleared");
  });
  cy.wait("@monitoringEndpoint");
  cy.getByLabel({ label: "exportCsvButton", tag: "button" }).click()
  cy.wait("@getResourceCount");
  cy.getByLabel({ label: "Export", tag: "button" }).click();
  cy.get(".MuiAlert-message").then(($snackbar) => {
    if ($snackbar.text().includes("Export processing in progress")) {
      cy.get(".MuiAlert-message").should("not.be.visible");
    }
  });
});

Then("a CSV file should be downloaded", () => {
  cy.waitUntil(
    () => cy.task("isDownloadComplete", { downloadsFolder }),
    {
      timeout: 20000,
      interval: 1000,
      errorMsg: "File not downloaded within the allotted time",
    }
  );
});

Then("the CSV file should contain the correct headers and the expected data", () => {
  cy.task("getExportedFile", { downloadsFolder }).then((filePath) => {
    cy.task("readCsvFile", { filePath }).then((csvContent) => {
      const rows = (csvContent as string)
        .trim()
        .split("\n")
        .map((row) => row.split(";").map((cell) => cell.trim()));

      const rawHeaders = rows[0];
      const headers = rawHeaders.map(normalize);
      cy.log("Normalized CSV Headers:", headers.join(" | "));
      expect(headers).to.deep.equal(ALL_COLUMNS);

      const dataRows = rows.slice(1);

      const rowObjects = dataRows.map((row) =>
        headers.reduce((obj, header, i) => {
          obj[header.replace(/"/g, "")] = row[i];
          return obj;
        }, {} as Record<string, string>)
      );
      cy.log("Formatted JSON from CSV:\n" + JSON.stringify(rowObjects, null, 2));
      const firstTwoRows = rowObjects.slice(0, 2);

      cy.fixture("resources/csvFIleWithAllPagesAndColumns.json").then((expectedData) => {
        const firstTwoExpected = expectedData.slice(0, 2);

        firstTwoRows.forEach((actualRow, index) => {
          const expectedRow = firstTwoExpected[index];

          COLUMNS_TO_COMPARE.forEach((key) => {
            expect(
              actualRow[key],
              `Line ${index + 1} - Key: ${key}`
            ).to.equal(expectedRow[key]);
          });
        });

        console.log("Partial field comparison successful");
      });
    });
  });
});

When("the admin user unchecks some columns in the table settings", () => {
  cy.getByTestId({ testId: "ViewColumnIcon" }).click();
  cy.waitForElementToBeVisible('[data-testid="RotateLeftIcon"]');
  const valuesToClick = [
	'Notes (N)',
	'Action (A)',
	'FQDN / Address',
	'Notification (Notif)',
	'Check (C)',
	'Duration',
	'Tries'
  ];

  valuesToClick.forEach((val) => {
	 cy.get(`li[value="${val}"]`).click();
  });
  cy.waitForRequestCount('getServicesStatus', 2, 10, 5000).then(() => {
      cy.log('Condition met: Request passed at least twice');
  });
 });

 Then("the admin user exports only visible columns and pages", () => {
  cy.task("clearDownloadsFolder", { downloadsFolder }).then(() => {
    cy.log("Downloads folder cleared");
  });
  cy.wait("@monitoringEndpoint");
  cy.getByLabel({ label: "exportCsvButton", tag: "button" }).click();
  cy.wait("@getResourceCount");
  cy.getByTestId({ testId: "Visible columns only" }).click();
  cy.getByTestId({ testId: "Current page only" }).click();
  cy.wait('@getResourceCount');
  cy.getByLabel({ label: "Export", tag: "button" }).click();
  cy.get(".MuiAlert-message").then(($snackbar) => {
    if ($snackbar.text().includes("Export processing in progress")) {
      cy.get(".MuiAlert-message").should("not.be.visible");
    }
  });
});

Then("the CSV file should contain the updated headers and the expected data", () => {
  cy.task("getExportedFile", { downloadsFolder }).then((filePath) => {
    cy.task("readCsvFile", { filePath }).then((csvContent) => {
      const rows = (csvContent as string)
        .trim()
        .split("\n")
        .map((row) => row.split(";").map((cell) => cell.trim()));

      const rawHeaders = rows[0];
      const headers = rawHeaders.map(normalize);
      cy.log("Normalized CSV Headers:", headers.join(" | "));
      expect(headers).to.deep.equal(UPDATED_COLUMNS);

      const dataRows = rows.slice(1);

      const rowObjects = dataRows.map((row) =>
        headers.reduce((obj, header, i) => {
          obj[header.replace(/"/g, "")] = row[i];
          return obj;
        }, {} as Record<string, string>)
      );
      cy.log("Formatted JSON from CSV:\n" + JSON.stringify(rowObjects, null, 2));
      const firstTwoRows = rowObjects.slice(0, 2);

      cy.fixture("resources/csvFIleWithOnlyVisiblePagesAndColumns.json").then((expectedData) => {
        const firstTwoExpected = expectedData.slice(0, 2);
        firstTwoRows.forEach((actualRow, index) => {
          const expectedRow = firstTwoExpected[index];

          UPDATED_COLUMNS.forEach((key) => {
            if (key === "Last Check") return;
            expect(
              actualRow[key],
              `Line ${index + 1} - Key: ${key}`
            ).to.equal(expectedRow[key]);
         });
       });
        console.log("Partial field comparison successful");
      });
    });
  });
});