/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from "@badeball/cypress-cucumber-preprocessor";

const services = {
	serviceCritical: {
		host: "host3",
		name: "service3",
		template: "SNMP-Linux-Load-Average",
	},
	serviceOk: { host: "host2", name: "service_test_ok", template: "Ping-LAN" },
	serviceWarning: {
		host: "host2",
		name: "service2",
		template: "SNMP-Linux-Memory",
	},
};
const checkResourcesDetails = (name, statu, firstIndex, secondIndex) => {
	cy.contains(name).click();
	cy.get("div.css-6tzyx9-header")
		.find("span.MuiChip-label")
		.should("have.text", statu);

	cy.get("div.css-h0171t-content")
		.eq(firstIndex)
		.find("h6")
		.should("have.text", "Status information");

	cy.get("div.css-h0171t-content")
		.eq(firstIndex)
		.find("p")
		.first()
		.should("have.text", "Output");

	cy.get("div.css-h0171t-content")
		.eq(secondIndex)
		.find("h6")
		.should("have.text", "Performance data");

	cy.get("div.css-h0171t-content")
		.eq(secondIndex)
		.find("p")
		.first()
		.should("have.text", "Performance data");
};

before(() => {
	cy.startContainers();
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
		.applyPollerConfiguration();
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
});

after(() => {
	cy.stopContainers();
});

Given("an admin user is logged in a Centreon server", () => {
	cy.loginByTypeOfUser({
		jsonName: "admin",
		loginViaApi: false,
	});
});

Given(
  'one passive service has been configured using arguments status and output exists',
  () => {
    cy.setPassiveResource('/centreon/api/latest/configuration/services/31');
  }
);

When("the user submits some results to this service", () => {
	cy.visit("/centreon/monitoring/resources");
	cy.get('input[placeholder="Search"]').clear().type("{enter}");
	cy.wait("@getResources");
	cy.getByTestId({ testId: "RefreshIcon" }).click();
	cy.wait("@getResources");
	cy.get('input[placeholder="Search"]')
		.clear()
		.type(`${services.serviceOk.name}{enter}`);
	cy.wait("@getResources");
	cy.getByTestId({ testId: "RefreshIcon" }).click();
	cy.wait("@getResources");
	cy.getByLabel({ label: "Select all" }).click();
	cy.get("button#Moreactions").click();
	cy.getByTestId({ testId: "Submit a status" }).click();
	cy.contains("div", "Ok").click();
	cy.contains("p", "Critical").click();
	cy.getByLabel({ label: "Output" }).type("Output");
	cy.getByLabel({ label: "Performance data" }).type("Performance data");
	cy.contains("button", "Submit").click();
	cy.waitUntil(
		() => {
			return cy
				.getByLabel({ label: "Critical status services", tag: "a" })
				.invoke("text")
				.then((text) => {
					if (text !== "1") {
						cy.exportConfig();
					}

					return text === "1";
				});
		},
		{ interval: 6000, timeout: 100000 },
	);
});

Then(
	"the values are set as wanted in Monitoring > Status details page of this service",
	() => {
		checkResourcesDetails(services.serviceOk.name, "Critical", 0, 10);
	},
);

Given(
  'one passive host has been configured using arguments status and output exists',
  () => {
    cy.setPassiveResource('/centreon/api/latest/configuration/hosts/15');
  }
);

When("the user submits some results to this host", () => {
	cy.visit("/centreon/monitoring/resources");
	cy.get('input[placeholder="Search"]').clear().type("{enter}");
	cy.getByTestId({ testId: "RefreshIcon" }).click();
	cy.get('input[placeholder="Search"]')
		.clear()
		.type(`type:host name:${services.serviceOk.host}{enter}`);
	cy.getByTestId({ testId: "RefreshIcon" }).click();
	cy.getByLabel({ label: "Select all" }).click();
	cy.get("button#Moreactions").click();
	cy.getByTestId({ testId: "Submit a status" }).click();
	cy.get('div[role="combobox"]').contains("Up").click();
	cy.contains("p", "Down").click();
	cy.getByLabel({ label: "Output" }).type("Output");
	cy.getByLabel({ label: "Performance data" }).type("Performance data");
	cy.contains("button", "Submit").click();
	cy.waitUntil(
		() => {
			return cy
				.getByLabel({ label: "Down status hosts", tag: "a" })
				.invoke("text")
				.then((text) => {
					if (text !== "1") {
						cy.exportConfig();
					}

					return text === "1";
				});
		},
		{ interval: 6000, timeout: 100000 },
	);
});

Then(
	"the values are set as wanted in Monitoring > Status details page of this host",
	() => {
		checkResourcesDetails(services.serviceOk.host, "Down", 0, 13);
	},
);
