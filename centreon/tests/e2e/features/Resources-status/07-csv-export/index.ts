/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from "@badeball/cypress-cucumber-preprocessor";;

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
  "Resource Type"
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
  "Check"
];

const normalize = (text: string) =>
  text.trim().replace(/^"|"$/g, '').replace(/\\"/g, '').replace(/"/g, '');

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
		"monitoringEndpoint",
	);

	// cy.startContainers();

	// cy.loginByTypeOfUser({
	// 	jsonName: "admin",
	// 	loginViaApi: true,
	// }).wait("@getFilters");

	// cy.disableListingAutoRefresh();

	// cy.addHost({
	// 	activeCheckEnabled: false,
	// 	checkCommand: "check_centreon_cpu",
	// 	name: "host1",
	// 	template: "generic-host",
	// })
	// 	.addService({
	// 		activeCheckEnabled: false,
	// 		host: "host1",
	// 		maxCheckAttempts: 1,
	// 		name: serviceInDtName,
	// 		template: "SNMP-DISK-/",
	// 	})
	// 	.addService({
	// 		activeCheckEnabled: false,
	// 		host: "host1",
	// 		maxCheckAttempts: 1,
	// 		name: secondServiceInDtName,
	// 		template: "Ping-LAN",
	// 	})
	// 	.addService({
	// 		activeCheckEnabled: false,
	// 		host: "host1",
	// 		maxCheckAttempts: 1,
	// 		name: serviceInAcknowledgementName,
	// 		template: "SNMP-DISK-/",
	// 	})
	// 	.addService({
	// 		activeCheckEnabled: false,
	// 		host: "host1",
	// 		maxCheckAttempts: 1,
	// 		name: serviceOk,
	// 		template: "Ping-LAN",
	// 	})
	// 	.applyPollerConfiguration();

	// checkServicesAreMonitored([
	// 	{
	// 		name: serviceOk,
	// 	},
	// ]);

	// cy.submitResults([
	// 	{
	// 		host: "host1",
	// 		output: "submit_status_2",
	// 		service: serviceInDtName,
	// 		status: "critical",
	// 	},
	// 	{
	// 		host: "host1",
	// 		output: "submit_status_2",
	// 		service: secondServiceInDtName,
	// 		status: "critical",
	// 	},
	// 	{
	// 		host: "host1",
	// 		output: "submit_status_2",
	// 		service: serviceInAcknowledgementName,
	// 		status: "critical",
	// 	},
	// 	{
	// 		host: "host1",
	// 		output: "submit_status_0",
	// 		service: serviceOk,
	// 		status: "ok",
	// 	},
	// ]);

	// checkServicesAreMonitored([
	// 	{
	// 		name: serviceInDtName,
	// 		status: "critical",
	// 	},
	// 	{
	// 		name: secondServiceInDtName,
	// 		status: "critical",
	// 	},
	// 	{
	// 		name: serviceInAcknowledgementName,
	// 		status: "critical",
	// 	},
	// 	{
	// 		name: serviceOk,
	// 		status: "ok",
	// 	},
	// ]);

	// ["Disk-/", "Load", "Memory", "Ping"].forEach((service) => {
	// 	cy.scheduleServiceCheck({ host: "Centreon-Server", service });
	// });

	// checkMetricsAreMonitored([
	// 	{
	// 		host: "Centreon-Server",
	// 		name: "rta",
	// 		service: "Ping",
	// 	},
	// ]);
	// cy.logoutViaAPI();
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
	 cy.intercept('/centreon/api/latest/monitoring/resources*')
	 .as('monitoringEndpoint');
	cy.intercept('GET', '**/monitoring/resources/count*').as('getResourceCount');
});

after(() => {
	cy.stopContainers();
});

Given("an admin user is logged in a Centreon server", () => {
	cy.loginByTypeOfUser({
		jsonName: "admin",
		loginViaApi: false,
	});
	cy.wait("@monitoringEndpoint");
	cy.getByTestId({ testId: "SaveAltIcon" }).click();
	cy.wait("@getResourceCount");
    cy.getByLabel({ label: "Export", tag: 'button' }).click();
	cy.get('.MuiAlert-message').then(($snackbar) => {
      if ($snackbar.text().includes('Export processing in progress')) {
        cy.wait('@getNavigationList');
        cy.get('.MuiAlert-message').should('not.be.visible');
      }
    });
	const downloadsFolder = Cypress.config('downloadsFolder');
	cy.task('getExportedFile', { downloadsFolder }).then(filePath => {
	cy.task('readCsvFile', { filePath }).then(csvContent => {
		const rows = (csvContent as string)
		.trim()
		.split('\n')
		.map(row => row.split(';').map(cell => cell.trim()));

		const rawHeaders  = rows[0];
		const headers = rawHeaders.map(normalize);
		// ✅ Log the headers (column names)
		cy.log('Normalized CSV Headers:', headers.join(' | '));
		expect(headers).to.deep.equal(ALL_COLUMNS);

		const dataRows = rows.slice(1);

		const rowObjects = dataRows.map(row => {
		return headers.reduce((obj, header, i) => {
			obj[header.replace(/"/g, '')] = row[i];
			return obj;
		}, {} as Record<string, string>);
		});
		cy.log('Formatted JSON from CSV:\n' + JSON.stringify(rowObjects, null, 2));
		const firstTwoRows = rowObjects.slice(0, 2);

		cy.fixture('resources/criticalRessources.json').then(expectedData => {
		const firstTwoExpected = expectedData.slice(0, 2);

		// 🔍 Partial comparison: only specific properties
		firstTwoRows.forEach((actualRow, index) => {
			const expectedRow = firstTwoExpected[index];

			COLUMNS_TO_COMPARE.forEach(key => {
			expect(actualRow[key], `Line ${index + 1} - Key: ${key}`).to.equal(expectedRow[key]);
			});
		});

		console.log('Partial field comparison successful');
		});
	});
	});
});
