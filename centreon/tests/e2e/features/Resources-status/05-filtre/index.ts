import { Given, Then, When } from "@badeball/cypress-cucumber-preprocessor";

import {
	checkMetricsAreMonitored,
	checkServicesAreMonitored,
} from "../../../commons";
import { setUserFilter } from "../common";

const serviceOk = "service_test_ok";
const serviceInDtName = "service_downtime_1";
const secondServiceInDtName = "service_downtime_2";
const serviceInAcknowledgementName = "service_ack_1";

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
	cy.loginByTypeOfUser({
		jsonName: "admin",
		loginViaApi: true,
	}).wait("@getFilters");
});

Given("a saved critical service filter", () => {
	cy.fixture("resources/criticalServicesFilter.json").then((filters) =>
		setUserFilter(filters),
	);

	cy.visit("centreon/monitoring/resources").wait([
		"@getFilters",
		"@monitoringEndpoint",
	]);

	cy.contains("Unhandled alerts").should("be.visible");

	cy.get(`div[data-testid="selectedFilter"]`).click();

	cy.contains("Critical_Services");
});

When("I select the critical service filter", () => {
	cy.contains("Critical_Services").click();
	cy.getByTestId({ testId: "RefreshIcon" }).click();
});

Then("only the critical services are displayed in the result", () => {
	cy.get('div[class*="statusColumn"]').each(($statusCell) => {
		cy.wrap($statusCell).should("contain.text", "Critical");
	});
});

Given("a saved pending host filter", () => {
	cy.fixture("resources/pendingHosts.json").then((filters) =>
		setUserFilter(filters),
	);

	cy.visit("centreon/monitoring/resources").wait([
		"@getFilters",
		"@monitoringEndpoint",
	]);

	cy.contains("Unhandled alerts").should("be.visible");

	cy.get(`div[data-testid="selectedFilter"]`).click();

	cy.contains("Pending_Hosts");
});

When("I select the pending host filter", () => {
	cy.contains("Pending_Hosts").click();
	cy.getByTestId({ testId: "RefreshIcon" }).click();
});

Then("only the pending hosts are displayed in the result", () => {
	cy.get('div[class*="statusColumn"]').each(($statusCell) => {
		cy.wrap($statusCell).should("contain.text", "Pending");
	});
});

Given("a saved up host filter", () => {
	cy.fixture("resources/upHosts.json").then((filters) =>
		setUserFilter(filters),
	);

	cy.visit("centreon/monitoring/resources").wait([
		"@getFilters",
		"@monitoringEndpoint",
	]);

	cy.contains("Unhandled alerts").should("be.visible");

	cy.get(`div[data-testid="selectedFilter"]`).click();

	cy.contains("Up_Hosts");
});

When("I select the up host filter", () => {
	cy.contains("Up_Hosts").click();
	cy.getByTestId({ testId: "RefreshIcon" }).click();
});

Then("only the up hosts are displayed in the result", () => {
	cy.get('div[class*="statusColumn"]').each(($statusCell) => {
		cy.wrap($statusCell).should("contain.text", "Up");
	});
});

Given(
	"a saved filter that includes a host group and all possible service statuses",
	() => {
		cy.fixture("resources/hostGroupAndServices.json").then((filters) =>
			setUserFilter(filters),
		);

		cy.visit("centreon/monitoring/resources").wait([
			"@getFilters",
			"@monitoringEndpoint",
		]);

		cy.contains("Unhandled alerts").should("be.visible");

		cy.get(`div[data-testid="selectedFilter"]`).click();

		cy.contains("HostGroupAndServices");
	},
);

When("I select host group filter with all service statuses", () => {
	cy.contains("HostGroupAndServices").click();
	cy.getByTestId({ testId: "RefreshIcon" }).click();
});

Then(
	"all associated services regardless of their status are shown in the result",
	() => {
	cy.waitForElementToBeVisible('div[class*="statusColumn"]:first').then(() => {
	cy.get('div[class*="statusColumn"]:first').invoke("text").should((text) => {
		expect(text.trim()).to.match(/Pending|Unknown/);
	  });
    });
	cy.get('div[class*="statusColumn"]').each(($statusCell, index) => {
		const cellText = $statusCell.text().trim();
		console.log(`Cell ${index}: ${cellText}`);

		expect(cellText).to.match(/^(Pending|OK|Unknown)$/);
	 });
	},
);

Given(
	"a saved filter that includes a host group and services with OK and Up statuses",
	() => {
		cy.fixture("resources/HostGroupWithUpOkStatuses.json").then((filters) =>
			setUserFilter(filters),
		);

		cy.visit("centreon/monitoring/resources").wait([
			"@getFilters",
			"@monitoringEndpoint",
		]);

		cy.contains("Unhandled alerts").should("be.visible");

		cy.get(`div[data-testid="selectedFilter"]`).click();

		cy.contains("HostGroupWithUpOkStatuses");
	},
);

When("I select the host group filter with OK and Up statuses", () => {
	cy.contains("HostGroupWithUpOkStatuses").click();
	cy.getByTestId({ testId: "RefreshIcon" }).click();
});

Then("only services with OK and Up statuses are shown in the result", () => {
	cy.waitForElementToBeVisible('div[class*="statusColumn"]:first').then(() => {
		cy.get('div[class*="statusColumn"]:first')
			.invoke("text")
			.then((text) => {
				expect(text.trim()).to.match(/^(Up|OK)$/);
			});
	});
	cy.get('div[class*="statusColumn"]').each(($statusCell, index) => {
		const cellText = $statusCell.text().trim();
		console.log(`Cell ${index}: ${cellText}`);
		expect(["OK", "Up"]).to.include(
			cellText,
			`Cell ${index} has unexpected text: ${cellText}`,
		);
	});
});

Given("a saved filter that includes Up hosts and Critical services", () => {
	cy.fixture("resources/upHostAndCriticalServiceFilter.json").then((filters) =>
		setUserFilter(filters),
	);

	cy.visit("centreon/monitoring/resources").wait([
		"@getFilters",
		"@monitoringEndpoint",
	]);

	cy.contains("Unhandled alerts").should("be.visible");

	cy.get(`div[data-testid="selectedFilter"]`).click();

	cy.contains("upHostAndCriticalServiceFilter");
});

When("I select the Up hosts and Critical services filter", () => {
	cy.contains("upHostAndCriticalServiceFilter").click();
	cy.getByTestId({ testId: "RefreshIcon" }).click();
});

Then(
	"only Critical services associated with Up hosts are shown in the result",
	() => {
		cy.waitForElementToBeVisible('div[class*="statusColumn"]:last').then(() => {
			cy.get('div[class*="statusColumn"]:last').should("contain.text", "Up");
		});
		cy.get('div[class*="statusColumn"]').each(($statusCell, index) => {
			const cellText = $statusCell.text().trim();
			console.log(`Cell ${index}: ${cellText}`);
			expect(["Critical", "Up"]).to.include(
				cellText,
				`Cell ${index} has unexpected text: ${cellText}`,
			);
		});
	},
);

Given(
	"a saved filter that includes a host a monitoring server  and services with OK status",
	() => {
		cy.fixture("resources/hostMonitoringServerOkStatus.json").then((filters) =>
			setUserFilter(filters),
		);

		cy.visit("centreon/monitoring/resources").wait([
			"@getFilters",
			"@monitoringEndpoint",
		]);

		cy.contains("Unhandled alerts").should("be.visible");

		cy.get(`div[data-testid="selectedFilter"]`).click();

		cy.contains("hostMonitoringServerOkStatus");
	},
);

When("I select the filter for the host monitoring server and OK status", () => {
	cy.contains("hostMonitoringServerOkStatus").click();
	cy.getByTestId({ testId: "RefreshIcon" }).click();
});

Then(
	"only services with OK status associated with the selected host and monitoring server are shown in the result",
	() => {
		cy.waitForElementToBeVisible('div[class*="statusColumn"]:first').then(
			() => {
				cy.get('div[class*="statusColumn"]:first').should("contain.text", "OK");
			},
		);
		cy.get('div[class*="statusColumn"]').each(($statusCell, index) => {
			const cellText = $statusCell.text().trim();
			console.log(`Cell ${index}: ${cellText}`);
			expect(["OK"]).to.include(
				cellText,
				`Cell ${index} has unexpected text: ${cellText}`,
			);
		});
	},
);

Given("a saved filter that includes a monitoring server with OK status", () => {
	cy.fixture("resources/monitoringServerAndOkStatus.json").then((filters) =>
		setUserFilter(filters),
	);

	cy.visit("centreon/monitoring/resources").wait([
		"@getFilters",
		"@monitoringEndpoint",
	]);

	cy.contains("Unhandled alerts").should("be.visible");

	cy.get(`div[data-testid="selectedFilter"]`).click();

	cy.contains("monitoringServerAndOkStatus");
});

When("I select the filter for the monitoring server with OK status", () => {
	cy.contains("monitoringServerAndOkStatus").click();
	cy.getByTestId({ testId: "RefreshIcon" }).click();
});

Then(
	"only services with OK status associated with the selected monitoring server are shown in the result",
	() => {
		cy.waitForElementToBeVisible('div[class*="statusColumn"]:first').then(
			() => {
				cy.get('div[class*="statusColumn"]:first').should("contain.text", "OK");
			},
		);
		cy.get('div[class*="statusColumn"]').each(($statusCell, index) => {
			const cellText = $statusCell.text().trim();
			console.log(`Cell ${index}: ${cellText}`);
			expect(["OK"]).to.include(
				cellText,
				`Cell ${index} has unexpected text: ${cellText}`,
			);
		});
	},
);

Given(
	"a saved filter that includes services with OK and Critical statuses",
	() => {
		cy.fixture("resources/criticalAndOkServicesFilter.json").then((filters) =>
			setUserFilter(filters),
		);

		cy.visit("centreon/monitoring/resources").wait([
			"@getFilters",
			"@monitoringEndpoint",
		]);

		cy.contains("Unhandled alerts").should("be.visible");

		cy.get(`div[data-testid="selectedFilter"]`).click();

		cy.contains("criticalAndOkServicesFilter");
	},
);

When("I select the filter for services with OK and Critical statuses", () => {
	cy.contains("criticalAndOkServicesFilter").click();
	cy.getByTestId({ testId: "RefreshIcon" }).click();
});

Then(
	"only services with OK and Critical statuses are shown in the result",
	() => {
		cy.waitForElementToBeVisible('div[class*="statusColumn"]:last').then(() => {
			cy.get('div[class*="statusColumn"]:last').should("contain.text", "OK");
		});
		cy.get('div[class*="statusColumn"]').each(($statusCell, index) => {
			const cellText = $statusCell.text().trim();
			console.log(`Cell ${index}: ${cellText}`);
			expect(["Critical", "OK"]).to.include(
				cellText,
				`Cell ${index} has unexpected text: ${cellText}`,
			);
		});
	},
);

Given(
	"a saved filter that includes acknowledged services with all states selected",
	() => {
		cy.fixture("resources/acknowledgeServiceAllStates.json").then((filters) =>
			setUserFilter(filters),
		);

		cy.visit("centreon/monitoring/resources").wait([
			"@getFilters",
			"@monitoringEndpoint",
		]);

		cy.contains("Unhandled alerts").should("be.visible");

		cy.get(`div[data-testid="selectedFilter"]`).click();

		cy.contains("acknowledgeServiceAllStates");
	},
);

When(
	"I apply the filter for acknowledged services with all states selected",
	() => {
		cy.contains("acknowledgeServiceAllStates").click();
		cy.getByTestId({ testId: "RefreshIcon" }).click();
	},
);

Then(
	"all acknowledged services with any state OK, Warning, Critical and Unknown are displayed in the results",
	() => {
		cy.waitForElementToBeVisible('div[class*="statusColumn"]:last').then(() => {
			cy.get('div[class*="statusColumn"]:last').should(
				"contain.text",
				"Critical",
			);
		});
		cy.get('div[class*="statusColumn"]').each(($statusCell, index) => {
			const cellText = $statusCell.text().trim();
			console.log(`Cell ${index}: ${cellText}`);
			expect(["Critical"]).to.include(
				cellText,
				`Cell ${index} has unexpected text: ${cellText}`,
			);
		});
	},
);

Given(
	"a saved filter that includes services with status OK and service category ping",
	() => {
		cy.fixture("resources/okServiceAndPingServiceCategory.json").then(
			(filters) => setUserFilter(filters),
		);

		cy.visit("centreon/monitoring/resources").wait([
			"@getFilters",
			"@monitoringEndpoint",
		]);

		cy.contains("Unhandled alerts").should("be.visible");

		cy.get(`div[data-testid="selectedFilter"]`).click();

		cy.contains("OkServiceAndPingServiceCategory");
	},
);

When(
	"I apply the filter for services with status OK and service category ping",
	() => {
		cy.contains("OkServiceAndPingServiceCategory").click();
		cy.getByTestId({ testId: "RefreshIcon" }).click();
	},
);

Then(
	"only services with status OK and belonging to the ping category are displayed in the results",
	() => {
		cy.waitForElementToBeVisible('div[class*="statusColumn"]:last').then(() => {
			cy.get('div[class*="statusColumn"]:last').should("contain.text", "OK");
		});
		cy.get('div[class*="statusColumn"]').each(($statusCell, index) => {
			const cellText = $statusCell.text().trim();
			console.log(`Cell ${index}: ${cellText}`);
			expect(["OK"]).to.include(
				cellText,
				`Cell ${index} has unexpected text: ${cellText}`,
			);
		});
	},
);

Given(
	"a saved filter that includes services with statuses OK and Critical and status types Hard and Soft",
	() => {
		cy.fixture("resources/hardSoftStatusFilter.json").then((filters) =>
			setUserFilter(filters),
		);

		cy.visit("centreon/monitoring/resources").wait([
			"@getFilters",
			"@monitoringEndpoint",
		]);

		cy.contains("Unhandled alerts").should("be.visible");

		cy.get(`div[data-testid="selectedFilter"]`).click();

		cy.contains("hardSoftStatusFilter");
	},
);

When(
	"I apply the filter for services with statuses OK and Critical and status types Hard and Soft",
	() => {
		cy.contains("hardSoftStatusFilter").click();
		cy.getByTestId({ testId: "RefreshIcon" }).click();
	},
);

Then(
	"only services with statuses OK and Critical and with status types Hard and Soft are displayed in the results",
	() => {
		cy.waitForElementToBeVisible('div[class*="statusColumn"]:last').then(() => {
			cy.get('div[class*="statusColumn"]:last').should("contain.text", "OK");
		});
		cy.get('div[class*="statusColumn"]').each(($statusCell, index) => {
			const cellText = $statusCell.text().trim();
			console.log(`Cell ${index}: ${cellText}`);
			expect(["OK", "Critical"]).to.include(
				cellText,
				`Cell ${index} has unexpected text: ${cellText}`,
			);
		});
	},
);

Given(
	"a saved filter that includes a specific host a specific service and a specific service category",
	() => {
		cy.fixture("resources/hostServiceServiceCategory.json").then((filters) =>
			setUserFilter(filters),
		);

		cy.visit("centreon/monitoring/resources").wait([
			"@getFilters",
			"@monitoringEndpoint",
		]);

		cy.contains("Unhandled alerts").should("be.visible");

		cy.get(`div[data-testid="selectedFilter"]`).click();

		cy.contains("hostServiceServiceCategory");
	},
);

When(
	"I apply the filter for the selected host service and service category",
	() => {
		cy.contains("hostServiceServiceCategory").click();
		cy.getByTestId({ testId: "RefreshIcon" }).click();
	},
);

Then(
	"only services matching the selected host service and service category are displayed in the results",
	() => {
		cy.waitForElementToBeVisible('div[class*="statusColumn"]:last').then(() => {
			cy.get('div[class*="statusColumn"]:last').should("contain.text", "OK");
		});
		cy.get('div[class*="statusColumn"]').each(($statusCell, index) => {
			const cellText = $statusCell.text().trim();
			console.log(`Cell ${index}: ${cellText}`);
			expect(["OK"]).to.include(
				cellText,
				`Cell ${index} has unexpected text: ${cellText}`,
			);
		});
	},
);

afterEach(() => {
	cy.deleteAllEventViewFilters();
	cy.logoutViaAPI();
});

after(() => {
	cy.stopContainers();
});
