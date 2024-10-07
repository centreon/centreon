import { renderHook } from "@testing-library/react-hooks/dom";
import { Provider, createStore, useAtomValue } from "jotai";
/* eslint-disable no-underscore-dangle */
/* eslint-disable @typescript-eslint/no-unused-expressions */
import * as Ramda from "ramda";
import { BrowserRouter as Router } from "react-router-dom";

import { Method, TestQueryProvider } from "@centreon/ui";
import {
	ListingVariant,
	aclAtom,
	platformFeaturesAtom,
	userAtom,
} from "@centreon/ui-context";

import { selectedVisualizationAtom } from "../Actions/actionsAtoms";
import useDetails from "../Details/useDetails";
import useFilter from "../Filter/useFilter";
import { Visualization } from "../models";
import {
	labelAcknowledged,
	labelAll,
	labelCancel,
	labelChecksDisabled,
	labelDowntime,
	labelInDowntime,
	labelOk,
	labelOnlyPassiveChecksEnabled,
	labelStartTime,
	labelViewByHost,
	labelViewByService,
} from "../translatedLabels";

import {
	defaultSelectedColumnIds,
	defaultSelectedColumnIdsforViewByHost,
} from "./columns";
import { selectedColumnIdsAtom } from "./listingAtoms";
import {
	acls,
	columnToSort,
	columns,
	entities,
	fakeData,
	getPlatformFeatures,
	retrievedListing,
	retrievedListingByHosts,
	retrievedListingWithCriticalResources,
} from "./testUtils";
import useLoadDetails from "./useLoadResources/useLoadDetails";

import Listing from ".";

const ListingTest = (): JSX.Element => {
	useFilter();
	useLoadDetails();
	useDetails();

	return (
		<div style={{ height: "100vh" }}>
			<Listing />
		</div>
	);
};

const store = createStore();

store.set(selectedVisualizationAtom, Visualization.All);
store.set(platformFeaturesAtom, getPlatformFeatures({}));
store.set(aclAtom, acls);

const ListingTestWithJotai = (): JSX.Element => (
	<Provider store={store}>
		<TestQueryProvider>
			<ListingTest />
		</TestQueryProvider>
	</Provider>
);

const configureUserAtomViewMode = (
	listingVariant: ListingVariant = ListingVariant.compact,
): void => {
	const userData = renderHook(() => useAtomValue(userAtom));

	userData.result.current.timezone = "Europe/Paris";
	userData.result.current.locale = "en_US";
	userData.result.current.user_interface_density = listingVariant;
};

before(() => {
	configureUserAtomViewMode();
});

const interceptRequestsAndMountBeforeEach = (
	interceptCriticalResources = false,
): void => {
	const responseForToListingTable = interceptCriticalResources
		? retrievedListingWithCriticalResources
		: retrievedListing;

	cy.interceptAPIRequest({
		alias: "filterRequest",
		method: Method.GET,
		path: "**/events-view*",
		response: fakeData,
	});
	cy.interceptAPIRequest({
		alias: "dataToListingTable",
		method: Method.GET,
		path: "**/resources?*",
		response: responseForToListingTable,
	});
	cy.mount({
		Component: (
			<Router>
				<ListingTestWithJotai />
			</Router>
		),
	});

	cy.viewport(1200, 1000);
};

describe("Resource Listing", () => {
	beforeEach(() => {
		configureUserAtomViewMode();
	});

	it("displays first part of information when multiple (split by \n) are available", () => {
		interceptRequestsAndMountBeforeEach();
		const [resourcesWithMultipleLines, resourcesWithSingleLines] =
			Ramda.partition(
				Ramda.where({ information: Ramda.includes("\n") }),
				retrievedListing.result,
			);
		cy.waitFiltersAndListingRequests();

		resourcesWithMultipleLines.forEach(({ information }) =>
			cy
				.contains(
					Ramda.pipe(
						Ramda.split("\n"),
						Ramda.head,
					)(information as string) as string,
				)
				.should("exist"),
		);
		resourcesWithSingleLines.forEach(({ information }) => {
			cy.contains(information as string).should("exist");
		});

		cy.makeSnapshot();
	});

	it("displays the listing in compact mode", () => {
		interceptRequestsAndMountBeforeEach();
		cy.waitFiltersAndListingRequests();

		cy.contains("E0").should("be.visible");

		cy.makeSnapshot();
	});

	it("displays the listing in extended mode", () => {
		interceptRequestsAndMountBeforeEach();
		configureUserAtomViewMode(ListingVariant.extended);
		cy.waitFiltersAndListingRequests();

		cy.contains("E0").should("be.visible");

		cy.makeSnapshot();
	});

	it("displays a highlighted row when a resource is in a critical state", () => {
		interceptRequestsAndMountBeforeEach(true);

		cy.waitFiltersAndListingRequests();

		cy.contains("E0").should("be.visible");

		cy.makeSnapshot();
	});

	it("reoders columns when a drag handle is focused and an arrow is pressed", () => {
		interceptRequestsAndMountBeforeEach();
		cy.waitFiltersAndListingRequests();

		cy.moveSortableElementUsingAriaLabel({
			ariaLabel: "Parent Drag handle",
			direction: "right",
		});

		cy.makeSnapshot();
	});
});

describe("Resource Listing: Visualization by Service", () => {
	beforeEach(() => {
		store.set(selectedVisualizationAtom, Visualization.All);
		interceptRequestsAndMountBeforeEach();
	});

	it('sends a request with types "service,metaservice"', () => {
		cy.findByLabelText(labelViewByService).click();

		cy.waitForRequest("@dataToListingTable").then(({ request }) => {
			expect(JSON.parse(request?.url?.searchParams.get("types"))).to.deep.equal(
				["service", "metaservice"],
			);
		});

		cy.makeSnapshot();
	});
	it("sorts columnns by worst status and duration", () => {
		cy.findByLabelText(labelViewByService).click();

		cy.waitForRequest("@dataToListingTable").then(({ request }) => {
			expect(
				JSON.parse(request?.url?.searchParams.get("sort_by")),
			).to.deep.equal({
				last_status_change: "desc",
				status_severity_code: "desc",
			});
		});

		cy.makeSnapshot();
	});

	it("disables columns drag and drop feature", () => {
		cy.findByLabelText(labelViewByService).click();

		cy.waitForRequest("@dataToListingTable");

		columns.forEach(({ label }) => {
			cy.findByLabelText(`${label} Drag handle`).should("not.exist");
		});

		cy.makeSnapshot();
	});

	it("updates column names", () => {
		cy.findByLabelText(labelViewByService).click();

		cy.waitForRequest("@dataToListingTable");

		cy.findByText("Resource").should("not.exist");
		cy.findByText("Parent").should("not.exist");
		cy.findByText("Service").should("be.visible");
		cy.findByText("Host").should("be.visible");

		cy.makeSnapshot();
	});
});

describe("Resource Listing: Visualization by Hosts", () => {
	after(() => {
		store.set(selectedColumnIdsAtom, defaultSelectedColumnIds);
		store.set(selectedVisualizationAtom, Visualization.All);
	});
	beforeEach(() => {
		store.set(selectedColumnIdsAtom, defaultSelectedColumnIdsforViewByHost);
		store.set(selectedVisualizationAtom, Visualization.Host);

		interceptRequestsAndMountBeforeEach();

		cy.interceptAPIRequest({
			alias: "listingByHosts",
			method: Method.GET,
			path: "**resources/hosts?**",
			response: retrievedListingByHosts,
		});
	});

	it("sends a request to retrieve all sevices and their parents", () => {
		cy.findByLabelText(labelViewByHost).click();

		cy.waitForRequest("@listingByHosts").then(({ request }) => {
			expect(JSON.parse(request?.url?.searchParams.get("types"))).to.deep.equal(
				["host"],
			);
		});

		cy.makeSnapshot();
	});

	it("sorts columnns by worst status and duration", () => {
		cy.findByLabelText(labelViewByHost).click();

		cy.waitForRequest("@listingByHosts").then(({ request }) => {
			expect(
				JSON.parse(request?.url?.searchParams.get("sort_by")),
			).to.deep.equal({
				last_status_change: "desc",
				status_severity_code: "desc",
			});
		});

		cy.makeSnapshot();
	});

	it("disables columns drag and drop feature", () => {
		cy.findByLabelText(labelViewByHost).click();

		cy.waitForRequest("@listingByHosts");

		columns.forEach(({ label }) => {
			cy.findByLabelText(`${label} Drag handle`).should("not.exist");
		});

		cy.makeSnapshot();
	});

	it("updates column names", () => {
		cy.findByLabelText(labelViewByHost).click();

		cy.waitForRequest("@listingByHosts");

		cy.findByText("Resource").should("not.exist");
		cy.findByText("Parent").should("not.exist");
		cy.findByText("State").should("be.visible");
		cy.findByText("Services").should("be.visible");
		cy.findByText("Host").should("be.visible");

		cy.makeSnapshot();
	});

	it("displays the services when the Expand button is clicked", () => {
		cy.findByLabelText(labelViewByHost).click();
		cy.waitForRequest("@listingByHosts");

		cy.findAllByLabelText("Expand 14").click();

		cy.findByText("Disk-/").should("be.visible");
		cy.findByText("Load").should("be.visible");
		cy.findByText("Memory").should("be.visible");
		cy.findByText("Ping").should("be.visible");

		cy.makeSnapshot();
	});
});

describe("Resource Listing: Visualization by all resources", () => {
	beforeEach(() => {
		store.set(selectedVisualizationAtom, Visualization.Service);
		interceptRequestsAndMountBeforeEach();
	});
	it("sends a request to get all resources", () => {
		cy.findByLabelText(labelAll).click();

		cy.waitForRequest("@dataToListingTable").then(({ request }) => {
			expect(request?.url?.searchParams.has("types")).to.be.false;
		});

		cy.makeSnapshot();
	});
	it("sorts columnns by newest duration", () => {
		cy.findByLabelText(labelAll).click();

		cy.waitForRequest("@dataToListingTable").then(({ request }) => {
			expect(
				JSON.parse(request?.url?.searchParams.get("sort_by")),
			).to.deep.equal({
				last_status_change: "desc",
			});
		});

		cy.makeSnapshot();
	});

	it("sets the column names to the default ones", () => {
		cy.findByLabelText(labelAll).click();

		cy.waitForRequest("@dataToListingTable");

		cy.findByText("Resource").should("be.visible");
		cy.findByText("Parent").should("be.visible");
		cy.findByText("Service").should("not.exist");
		cy.findByText("Host").should("not.exist");

		cy.makeSnapshot();
	});

	it("enables columns drag and drop feature", () => {
		cy.findByLabelText(labelAll).click();

		cy.waitForRequest("@dataToListingTable");

		columnToSort.forEach(({ label }) => {
			cy.findByLabelText(`${label} Drag handle`).should("exist");
		});

		cy.makeSnapshot();
	});
});

describe("column sorting", () => {
	beforeEach(() => {
		columnToSort.forEach(() => {
			cy.interceptAPIRequest({
				alias: `dataToListingTable`,
				method: Method.GET,
				path: "./api/latest/monitoring**",
				response: retrievedListing,
			});
		});

		cy.interceptAPIRequest({
			alias: "filterRequest",
			method: Method.GET,
			path: "**/events-view*",
			response: fakeData,
		});

		cy.mount({
			Component: (
				<Router>
					<ListingTestWithJotai />
				</Router>
			),
		});

		cy.viewport(1200, 1000);
	});

	columnToSort.forEach(({ label }) => {
		it(`executes a listing request with sort_by param and stores the order parameter in the URL when ${label} column is clicked`, () => {
			cy.waitForRequest("@filterRequest");

			cy.findByLabelText(`Column ${label}`).click();

			cy.waitForRequest(`@dataToListingTable`);

			cy.findByLabelText(`Column ${label}`).click();

			cy.waitForRequest(`@dataToListingTable`);

			cy.makeSnapshot();
		});
	});
});

const pageNavigationCalls = [
	{ expectedCall: 1, param: "page=2&limit=30" },
	{ expectedCall: 4, param: "page=1&limit=30" },
	{ expectedCall: 1, param: "page=4&limit=30" },
];

describe("Listing request", () => {
	beforeEach(() => {
		interceptRequestsAndMountBeforeEach();
	});

	it("executes a listing request with an updated page param when a change page action is clicked", () => {
		cy.waitFiltersAndListingRequests();

		cy.findByLabelText(`Next page`)
			.should((label) => {
				expect(label).to.be.enabled;
			})
			.click();

		cy.waitForRequest("@dataToListingTable");

		cy.findByLabelText(`Previous page`)
			.should((label) => {
				expect(label).to.be.enabled;
			})
			.click();

		cy.waitForRequest("@dataToListingTable");

		cy.findByLabelText(`Last page`)
			.should((label) => {
				expect(label).to.be.enabled;
			})
			.click();

		cy.waitForRequest("@dataToListingTable");

		cy.findByLabelText(`First page`)
			.should((label) => {
				expect(label).to.be.enabled;
			})
			.click();

		cy.waitForRequest("@dataToListingTable");

		cy.getRequestCalls("@dataToListingTable").then((calls) => {
			expect(calls).to.have.length(6);
			pageNavigationCalls.forEach(({ param, expectedCall }) => {
				expect(
					Ramda.filter(
						(call) => Ramda.includes(param, call.request.url.search),
						calls,
					),
				).to.have.length(expectedCall);
			});
		});

		cy.makeSnapshot();
	});

	it("executes a listing request with a limit param when the rows per page value is changed", () => {
		cy.waitFiltersAndListingRequests();

		cy.get("#Rows\\ per\\ page").click();
		cy.contains(/^30$/).click({ force: true });

		cy.waitForRequest("@dataToListingTable").then(({ request }) => {
			expect(Ramda.includes("&limit=30", request.url.search)).to.be.true;
		});

		cy.makeSnapshot();
	});
});

describe("Display additional columns", () => {
	beforeEach(() => {
		cy.interceptAPIRequest({
			alias: "downtimeRequest",
			method: Method.GET,
			path: "**/monitoring/downtime*",
			response: {
				result: [
					{
						author_name: "admin",
						comment: "Set by admin",
						end_time: "2020-02-28T08:18:16Z",
						id: 0,
						is_fixed: true,
						start_time: "2020-02-28T08:16:16Z",
					},
				],
			},
		});
		cy.interceptAPIRequest({
			alias: "acknowledgeRequest",
			method: Method.GET,
			path: "**/monitoring/acknowledgement*",
			response: {
				result: [
					{
						author_name: "admin",
						comment: "Set by admin",
						entry_time: "2020-02-28T08:16:00Z",
						id: 0,
						is_persistent_comment: true,
						is_sticky: false,
					},
				],
			},
		});
		interceptRequestsAndMountBeforeEach();
	});

	it("displays downtime details when the downtime state chip is hovered", () => {
		cy.waitFiltersAndListingRequests();

		const entityInDowntime = entities.find(
			({ is_in_downtime }) => is_in_downtime,
		);

		const chipLabel = `${entityInDowntime?.name} ${labelInDowntime}`;

		cy.findByLabelText("Add columns").click();

		cy.contains("State").click();

		cy.findByLabelText("Add columns").click();

		cy.findByLabelText(chipLabel).trigger("mouseover");

		cy.waitForRequest("@downtimeRequest").then(({ request }) => {
			expect(
				Ramda.includes(
					request.url.pathname,
					entityInDowntime?.links?.endpoints.downtime as string,
				),
			).to.be.true;
		});

		cy.findByText("admin").should("be.visible");
		cy.findByText("Yes").should("be.visible");
		cy.findByText("02/28/2020 9:16 AM").should("be.visible");
		cy.findByText("02/28/2020 9:18 AM").should("be.visible");
		cy.findByText("Set by admin").should("be.visible");

		cy.makeSnapshot();
	});

	it("displays acknowledgement details when an acknowledged state chip is hovered", () => {
		cy.waitFiltersAndListingRequests();

		const acknowledgedEntity = entities.find(
			({ is_acknowledged }) => is_acknowledged,
		);

		cy.findByLabelText("Add columns").click();

		cy.contains("State").click();

		cy.findByLabelText("Add columns").click();

		const chipLabel = `${acknowledgedEntity?.name} ${labelAcknowledged}`;

		cy.findByLabelText(chipLabel).trigger("mouseover");

		cy.waitForRequest("@acknowledgeRequest").then(({ request }) => {
			expect(
				Ramda.includes(
					request.url.pathname,
					acknowledgedEntity?.links?.endpoints.acknowledgement as string,
				),
			).to.be.true;
		});

		cy.findByText("admin").should("be.visible");
		cy.findByText("Yes").should("be.visible");
		cy.findByText("02/28/2020 9:16 AM").should("be.visible");
		cy.findByText("No").should("be.visible");
		cy.findByText("Set by admin").should("be.visible");

		cy.makeSnapshot();
	});

	const columnIds = Ramda.map(Ramda.prop("id"), columns);

	const additionalIds = Ramda.reject(
		Ramda.includes(Ramda.__, [...defaultSelectedColumnIds, "state"]),
		columnIds,
	);

	additionalIds.forEach((columnId) => {
		it(`displays additional columns when selected from the corresponding ${columnId} menu`, () => {
			cy.waitFiltersAndListingRequests();

			cy.findByLabelText("Add columns").click();

			const column = Ramda.find(Ramda.propEq("id", columnId), columns);
			const columnLabel = column?.label as string;

			const columnShortLabel = column?.shortLabel as string;

			const hasShortLabel = !Ramda.isNil(columnShortLabel);

			const columnDisplayLabel = hasShortLabel
				? `${columnLabel} (${columnShortLabel})`
				: columnLabel;

			cy.findAllByText(columnDisplayLabel).eq(-1).click();

			const expectedLabelCount = hasShortLabel ? 1 : 2;

			cy.findAllByText(columnDisplayLabel).should(
				"have.length",
				expectedLabelCount,
			);

			if (hasShortLabel) {
				cy.findByText(columnDisplayLabel).should("be.visible");
			}

			cy.makeSnapshot();
		});
	});
});

describe("Notification column", () => {
	it("displays notification column if the cloud notification feature is disabled", () => {
		store.set(
			platformFeaturesAtom,
			getPlatformFeatures({ notification: false }),
		);
		interceptRequestsAndMountBeforeEach();

		cy.waitFiltersAndListingRequests();

		cy.contains("E0").should("be.visible");

		cy.findByTestId("Add columns").click();

		cy.findByText("Notification (Notif)").should("exist");

		cy.makeSnapshot();
	});

	it("hides notification column if the cloud notification feature is enabled", () => {
		store.set(
			platformFeaturesAtom,
			getPlatformFeatures({ notification: true }),
		);
		interceptRequestsAndMountBeforeEach();

		cy.waitFiltersAndListingRequests();

		cy.contains("E0").should("be.visible");

		cy.findByTestId("Add columns").click();

		cy.findByText("Severity (S)").should("exist");
		cy.findByText("Notification (Notif)").should("not.exist");

		cy.makeSnapshot();
	});
});

describe("Tree view : Feature Flag", () => {
	it("hides the tree view icons if the feature is disabled", () => {
		store.set(
			platformFeaturesAtom,
			getPlatformFeatures({ enableTreeView: false }),
		);
		interceptRequestsAndMountBeforeEach();

		cy.contains("E0").should("be.visible");

		cy.findByTestId("tree view").should("not.exist");

		cy.makeSnapshot();
	});
	it("displays the tree view icons if the feature is enabled", () => {
		store.set(
			platformFeaturesAtom,
			getPlatformFeatures({ enableTreeView: true }),
		);
		interceptRequestsAndMountBeforeEach();

		cy.contains("E0").should("be.visible");

		cy.findByTestId("tree view").should("be.visible");

		cy.makeSnapshot();
	});
});

describe("Checks icon", () => {
	beforeEach(() => {
		store.set(selectedColumnIdsAtom, ["checks", ...defaultSelectedColumnIds]);
		cy.interceptAPIRequest({
			alias: "filterRequest",
			method: Method.GET,
			path: "**/events-view*",
			response: fakeData,
		});
		cy.fixture("resources/listing/checksIcon.json").then((data) => {
			cy.interceptAPIRequest({
				alias: "getListing",
				method: Method.GET,
				path: "**/resources?*",
				response: data,
			});
		});

		cy.mount({
			Component: (
				<Router>
					<ListingTestWithJotai />
				</Router>
			),
		});
	});

	[
		{
			condition: "active and  passive checks are false",
			iconTitle: labelChecksDisabled,
			testId: "SyncDisabledIcon",
		},
		{
			condition: "active checks is false",
			iconTitle: labelOnlyPassiveChecksEnabled,
			testId: "SyncProblemIcon",
		},
	].forEach(({ iconTitle, condition, testId }) => {
		it(`displays the check icon ${iconTitle} when the ${condition} `, () => {
			cy.waitForRequest("@getListing");
			cy.findByTestId(testId).should("be.visible");
			cy.makeSnapshot(`displays the check icon ${iconTitle} when ${condition}`);
		});
	});
});

const openCalendar = (label): void => {
	cy.findByLabelText(label)
		.find("input")
		.then(($input) => {
			if ($input.attr("readonly")) {
				cy.wrap($input).click();
			} else {
				cy.findByLabelText(label).findByRole("button").click();
			}
		});
};

const timeZoneCases = [
	{
		timezone: "Europe/Paris",
		expectedInitialInputValue: "07/27/2024 06:04 AM",
		expectedFinalInputValue: "07/20/2024 06:04 AM",
	},
	{
		timezone: "UTC",
		expectedInitialInputValue: "07/27/2024 04:04 AM",
		expectedFinalInputValue: "07/20/2024 04:04 AM",
	},
];

describe.only("", () => {
	const now = new Date(Date.UTC(2024, 6, 27, 4, 4, 33));
	beforeEach(() => {
		interceptRequestsAndMountBeforeEach();

		cy.waitFiltersAndListingRequests();
		cy.clock(now);
	});

	for (const timeZoneCase of timeZoneCases) {
		const { timezone, expectedInitialInputValue, expectedFinalInputValue } =
			timeZoneCase;

		it(`checks the datepicker of downtime when  setting a downtime action for a resource for ${timezone} `, () => {
			// cy.clock(now).then((clock) => {
			const userData = renderHook(() => useAtomValue(userAtom));

			userData.result.current.timezone = timezone;

			cy.findByLabelText("Select row 4").trigger("mouseover");
			cy.findByTestId("Set downtime on E4").click();

			cy.contains("Downtime set by");
			cy.contains(labelCancel);
			cy.contains(labelDowntime);

			cy.findByLabelText(labelStartTime)
				.find("input")
				.should("have.value", expectedInitialInputValue);

			openCalendar(labelStartTime);

			cy.findByRole("gridcell", { name: "27" }).should(
				"have.attr",
				"aria-selected",
				"true",
			);

			cy.findByRole("gridcell", { name: "20" }).click();

			cy.findByRole("button", { name: "OK" }).click();

			cy.findByLabelText(labelStartTime)
				.find("input")
				.should("have.value", expectedFinalInputValue);

			openCalendar(labelStartTime);

			cy.findByRole("gridcell", { name: "20" }).should(
				"have.attr",
				"aria-selected",
				"true",
			);
			cy.findByRole("gridcell", { name: "27" }).should(
				"have.attr",
				"aria-current",
				"date",
			);
			cy.findByRole("button", { name: "OK" }).click();

			cy.findByRole("button", { name: labelCancel }).click({ force: true });
			// clock.restore();
			// }
			// );
		});
	}
});
