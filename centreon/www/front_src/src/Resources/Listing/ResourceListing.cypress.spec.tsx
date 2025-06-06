import { Provider, createStore } from 'jotai';
import { BrowserRouter as Router } from 'react-router';

import { Method, TestQueryProvider } from '@centreon/ui';
import {
  ListingVariant,
  platformFeaturesAtom,
  userAtom
} from '@centreon/ui-context';

import { selectedVisualizationAtom } from '../Actions/actionsAtoms';
import useDetails from '../Details/useDetails';
import useFilter from '../Filter/useFilter';
import { Visualization } from '../models';
import {
  labelAcknowledged,
  labelAll,
  labelInDowntime,
  labelResourceFlapping,
  labelViewByHost,
  labelViewByService
} from '../translatedLabels';

import {
  defaultSelectedColumnIds,
  defaultSelectedColumnIdsforViewByHost
} from './columns';
import { selectedColumnIdsAtom } from './listingAtoms';
import {
  columnToSort,
  columns,
  entities,
  fakeData,
  getPlatformFeatures,
  retrievedListing,
  retrievedListingByHosts,
  retrievedListingWithCriticalResources
} from './testUtils';
import useLoadDetails from './useLoadResources/useLoadDetails';

import {
  __,
  equals,
  filter,
  find,
  head,
  includes,
  isNil,
  map,
  partition,
  pipe,
  prop,
  propEq,
  reject,
  split,
  where
} from 'ramda';
import Listing from '.';

const pageNavigationCalls = [
  { expectedCall: 1, param: 'page=2&limit=30' },
  { expectedCall: 4, param: 'page=1&limit=30' },
  { expectedCall: 1, param: 'page=4&limit=30' }
];

const configureUserAtomViewMode = (
  listingVariant: ListingVariant = ListingVariant.compact
): void => {
  store.set(userAtom, {
    user_interface_density: listingVariant,
    locale: 'en_US',
    timezone: 'Europe/Paris'
  });
};

before(() => {
  configureUserAtomViewMode();
});

const ListingTest = (): JSX.Element => {
  useFilter();
  useLoadDetails();
  useDetails();

  return (
    <div style={{ height: '100vh' }}>
      <Listing />
    </div>
  );
};

const store = createStore();

store.set(selectedVisualizationAtom, Visualization.All);
store.set(platformFeaturesAtom, getPlatformFeatures({}));

const ListingTestWithJotai = (): JSX.Element => (
  <Provider store={store}>
    <TestQueryProvider>
      <ListingTest />
    </TestQueryProvider>
  </Provider>
);

const interceptRequestsAndMountBeforeEach = (
  interceptCriticalResources = false
): void => {
  const responseForToListingTable = interceptCriticalResources
    ? retrievedListingWithCriticalResources
    : retrievedListing;

  cy.interceptAPIRequest({
    alias: 'filterRequest',
    method: Method.GET,
    path: '**/events-view*',
    response: fakeData
  });
  cy.interceptAPIRequest({
    alias: 'dataToListingTable',
    method: Method.GET,
    path: '**/resources?*',
    response: responseForToListingTable
  });
  cy.mount({
    Component: (
      <Router>
        <ListingTestWithJotai />
      </Router>
    )
  });

  cy.adjustViewport();
};

describe('Resource Listing', () => {
  beforeEach(() => {
    configureUserAtomViewMode();
  });

  it('displays first part of information when multiple (split by \n) are available', () => {
    interceptRequestsAndMountBeforeEach();
    const [resourcesWithMultipleLines, resourcesWithSingleLines] = partition(
      where({ information: includes('\n') }),
      retrievedListing.result
    );
    cy.waitFiltersAndListingRequests();

    resourcesWithMultipleLines.forEach(({ information }) =>
      cy
        .contains(pipe(split('\n'), head)(information as string) as string)
        .should('exist')
    );
    resourcesWithSingleLines.forEach(({ information }) => {
      cy.contains(information as string).should('exist');
    });

    cy.makeSnapshot();
  });

  it('displays the listing in compact mode', () => {
    interceptRequestsAndMountBeforeEach();
    cy.waitFiltersAndListingRequests();

    cy.contains('E0').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the listing in extended mode', () => {
    interceptRequestsAndMountBeforeEach();
    configureUserAtomViewMode(ListingVariant.extended);
    cy.waitFiltersAndListingRequests();

    cy.contains('E0').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a highlighted row when a resource is in a critical state', () => {
    interceptRequestsAndMountBeforeEach(true);

    cy.waitFiltersAndListingRequests();

    cy.contains('E0').should('be.visible');

    cy.makeSnapshot();
  });

  it('reorders columns when a drag handle is focused and an arrow is pressed', () => {
    interceptRequestsAndMountBeforeEach();
    cy.waitFiltersAndListingRequests();

    cy.moveSortableElementUsingAriaLabel({
      ariaLabel: 'Parent Drag handle',
      direction: 'right'
    });

    cy.makeSnapshot();
  });
});

describe('Resource Listing: Visualization by Service', () => {
  beforeEach(() => {
    store.set(selectedVisualizationAtom, Visualization.All);
    interceptRequestsAndMountBeforeEach();
  });

  it('sends a request with types "service,metaservice"', () => {
    cy.findByTestId('tree view').should('be.visible');

    cy.findByLabelText(labelViewByService).click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      expect(JSON.parse(request?.url?.searchParams.get('types'))).to.deep.equal(
        ['service', 'metaservice']
      );
    });

    cy.makeSnapshot();
  });
  it('sorts columnns by worst status and duration', () => {
    cy.findByLabelText(labelViewByService).click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      expect(
        JSON.parse(request?.url?.searchParams.get('sort_by'))
      ).to.deep.equal({
        last_status_change: 'desc',
        status_severity_code: 'desc'
      });
    });

    cy.makeSnapshot();
  });

  it('disables columns drag and drop feature', () => {
    cy.findByLabelText(labelViewByService).click();

    cy.waitForRequest('@dataToListingTable');

    columns.forEach(({ label }) => {
      cy.findByLabelText(`${label} Drag handle`).should('not.exist');
    });

    cy.makeSnapshot();
  });

  it('updates column names', () => {
    cy.findByLabelText(labelViewByService).click();

    cy.waitForRequest('@dataToListingTable');

    cy.findByText('Resource').should('not.exist');
    cy.findByText('Parent').should('not.exist');
    cy.findByText('Service').should('be.visible');
    cy.findByText('Host').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Resource Listing: Visualization by Hosts', () => {
  after(() => {
    store.set(selectedColumnIdsAtom, defaultSelectedColumnIds);
    store.set(selectedVisualizationAtom, Visualization.All);
  });
  beforeEach(() => {
    store.set(selectedColumnIdsAtom, defaultSelectedColumnIdsforViewByHost);
    store.set(selectedVisualizationAtom, Visualization.Host);

    interceptRequestsAndMountBeforeEach();

    cy.interceptAPIRequest({
      alias: 'listingByHosts',
      method: Method.GET,
      path: '**resources/hosts?**',
      response: retrievedListingByHosts
    });
  });

  it('sends a request to retrieve all sevices and their parents', () => {
    cy.findByLabelText(labelViewByHost).click();

    cy.waitForRequest('@listingByHosts').then(({ request }) => {
      expect(JSON.parse(request?.url?.searchParams.get('types'))).to.deep.equal(
        ['host']
      );
    });

    cy.makeSnapshot();
  });

  it('sorts columnns by worst status and duration', () => {
    cy.findByLabelText(labelViewByHost).click();

    cy.waitForRequest('@listingByHosts').then(({ request }) => {
      expect(
        JSON.parse(request?.url?.searchParams.get('sort_by'))
      ).to.deep.equal({
        last_status_change: 'desc',
        status_severity_code: 'desc'
      });
    });

    cy.makeSnapshot();
  });

  it('disables columns drag and drop feature', () => {
    cy.findByLabelText(labelViewByHost).click();

    cy.waitForRequest('@listingByHosts');

    columns.forEach(({ label }) => {
      cy.findByLabelText(`${label} Drag handle`).should('not.exist');
    });

    cy.makeSnapshot();
  });

  it('updates column names', () => {
    cy.findByLabelText(labelViewByHost).click();

    cy.waitForRequest('@listingByHosts');

    cy.findByText('Resource').should('not.exist');
    cy.findByText('Parent').should('not.exist');
    cy.findByText('State').should('be.visible');
    cy.findByText('Services').should('be.visible');
    cy.findByText('Host').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the services when the Expand button is clicked', () => {
    cy.findByLabelText(labelViewByHost).click();
    cy.waitForRequest('@listingByHosts');

    cy.findAllByLabelText('Expand 14').click();

    cy.findByText('Disk-/').should('be.visible');
    cy.findByText('Load').should('be.visible');
    cy.findByText('Memory').should('be.visible');
    cy.findByText('Ping').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Resource Listing: Visualization by all resources', () => {
  beforeEach(() => {
    store.set(selectedVisualizationAtom, Visualization.Service);
    interceptRequestsAndMountBeforeEach();
  });
  it('sends a request to get all resources', () => {
    cy.findByLabelText(labelAll).click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      expect(request?.url?.searchParams.has('types')).to.be.false;
    });

    cy.makeSnapshot();
  });
  it('sorts columnns by newest duration', () => {
    cy.findByLabelText(labelAll).click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      expect(
        JSON.parse(request?.url?.searchParams.get('sort_by'))
      ).to.deep.equal({
        last_status_change: 'desc'
      });
    });

    cy.makeSnapshot();
  });

  it('sets the column names to the default ones', () => {
    cy.findByLabelText(labelAll).click();

    cy.waitForRequest('@dataToListingTable');

    cy.findByText('Resource').should('be.visible');
    cy.findByText('Parent').should('be.visible');
    cy.findByText('Service').should('not.exist');
    cy.findByText('Host').should('not.exist');

    cy.makeSnapshot();
  });

  it('enables columns drag and drop feature', () => {
    cy.findByLabelText(labelAll).click();

    cy.waitForRequest('@dataToListingTable');

    columnToSort.forEach(({ label }) => {
      cy.findByLabelText(`${label} Drag handle`).should('exist');
    });

    cy.makeSnapshot();
  });
});

describe('column sorting', () => {
  beforeEach(() => {
    cy.adjustViewport();
    columnToSort.forEach(() => {
      cy.interceptAPIRequest({
        alias: 'dataToListingTable',
        method: Method.GET,
        path: './api/latest/monitoring**',
        response: retrievedListing
      });
    });

    cy.interceptAPIRequest({
      alias: 'filterRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: fakeData
    });

    cy.mount({
      Component: (
        <Router>
          <ListingTestWithJotai />
        </Router>
      )
    });
  });

  columnToSort.forEach(({ label }) => {
    it(`executes a listing request with sort_by param and stores the order parameter in the URL when ${label} column is clicked`, () => {
      cy.waitForRequest('@filterRequest');

      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequest('@dataToListingTable');

      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequest('@dataToListingTable');

      cy.get('div[class*="MuiTable-root"]').parent().scrollTo('top');

      cy.makeSnapshot();
    });
  });
});

describe('Listing request', () => {
  beforeEach(() => {
    interceptRequestsAndMountBeforeEach();
  });

  it('executes a listing request with an updated page param when a change page action is clicked', () => {
    cy.waitFiltersAndListingRequests();

    cy.findByLabelText('Next page')
      .should((label) => {
        expect(label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable');

    cy.findByLabelText('Previous page')
      .should((label) => {
        expect(label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable');

    cy.findByLabelText('Last page')
      .should((label) => {
        expect(label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable');

    cy.findByLabelText('First page')
      .should((label) => {
        expect(label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable');

    cy.getRequestCalls('@dataToListingTable').then((calls) => {
      expect(calls).to.have.length(6);
      pageNavigationCalls.forEach(({ param, expectedCall }) => {
        expect(
          filter((call) => includes(param, call.request.url.search), calls)
        ).to.have.length(expectedCall);
      });
    });

    cy.makeSnapshot();
  });

  it('executes a listing request with a limit param when the rows per page value is changed', () => {
    cy.waitFiltersAndListingRequests();

    cy.get('#Rows\\ per\\ page').click();
    cy.contains(/^30$/).click({ force: true });

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      expect(includes('&limit=30', request.url.search)).to.be.true;
    });

    cy.makeSnapshot();
  });
});

describe('Display additional columns', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'downtimeRequest',
      method: Method.GET,
      path: '**/monitoring/downtime*',
      response: {
        result: [
          {
            author_name: 'admin',
            comment: 'Set by admin',
            end_time: '2020-02-28T08:18:16Z',
            id: 0,
            is_fixed: true,
            start_time: '2020-02-28T08:16:16Z'
          }
        ]
      }
    });
    cy.interceptAPIRequest({
      alias: 'acknowledgeRequest',
      method: Method.GET,
      path: '**/monitoring/acknowledgement*',
      response: {
        result: [
          {
            author_name: 'admin',
            comment: 'Set by admin',
            entry_time: '2020-02-28T08:16:00Z',
            id: 0,
            is_persistent_comment: true,
            is_sticky: false
          }
        ]
      }
    });
    interceptRequestsAndMountBeforeEach();
  });

  it('displays downtime details when the downtime state chip is hovered', () => {
    cy.waitFiltersAndListingRequests();

    const entityInDowntime = entities.find(
      ({ is_in_downtime }) => is_in_downtime
    );

    const chipLabel = `${entityInDowntime?.name} ${labelInDowntime}`;

    cy.findByLabelText('Add columns').click();

    cy.contains('State').click();

    cy.findByLabelText('Add columns').click();

    cy.findByLabelText(chipLabel).trigger('mouseover');

    cy.waitForRequest('@downtimeRequest').then(({ request }) => {
      expect(
        includes(
          request.url.pathname,
          entityInDowntime?.links?.endpoints.downtime as string
        )
      ).to.be.true;
    });

    cy.findByText('admin').should('be.visible');
    cy.findByText('Yes').should('be.visible');
    cy.findByText('02/28/2020 9:16 AM').should('be.visible');
    cy.findByText('02/28/2020 9:18 AM').should('be.visible');
    cy.findByText('Set by admin').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays acknowledgement details when an acknowledged state chip is hovered', () => {
    cy.waitFiltersAndListingRequests();

    const acknowledgedEntity = entities.find(
      ({ is_acknowledged }) => is_acknowledged
    );

    cy.findByLabelText('Add columns').click();

    cy.contains('State').click();

    cy.findByLabelText('Add columns').click();

    const chipLabel = `${acknowledgedEntity?.name} ${labelAcknowledged}`;

    cy.findByLabelText(chipLabel).trigger('mouseover');

    cy.waitForRequest('@acknowledgeRequest').then(({ request }) => {
      expect(
        includes(
          request.url.pathname,
          acknowledgedEntity?.links?.endpoints.acknowledgement as string
        )
      ).to.be.true;
    });

    cy.findByText('admin').should('be.visible');
    cy.findByText('Yes').should('be.visible');
    cy.findByText('02/28/2020 9:16 AM').should('be.visible');
    cy.findByText('No').should('be.visible');
    cy.findByText('Set by admin').should('be.visible');

    cy.makeSnapshot();

    cy.findByLabelText(chipLabel).trigger('mouseout');

    cy.get('[value="Information"]').click();
    cy.get('[value="Status"]').click();
    cy.get('[value="Resource"]').click();
    cy.get('[value="Parent"]').click();
    cy.get('[value="Graph (G)"]').click();
    cy.get('[value="Duration"]').click();
    cy.get('[value="Last check"]').click();
    cy.get('[value="Tries"]').click();

    cy.findByLabelText('Add columns').click();
  });

  const columnIds = map(prop('id'), columns);

  const additionalIds = reject(
    includes(__, [...defaultSelectedColumnIds, 'state']),
    columnIds
  );

  additionalIds.forEach((columnId) => {
    it(`displays additional columns when selected from the corresponding ${columnId} menu`, () => {
      cy.waitFiltersAndListingRequests();

      cy.findByLabelText('Add columns').click();

      const column = find(propEq(columnId, 'id'), columns);
      const columnLabel = column?.label as string;

      const columnShortLabel = column?.shortLabel as string;

      const hasShortLabel = !isNil(columnShortLabel);

      const columnDisplayLabel = hasShortLabel
        ? `${columnLabel} (${columnShortLabel})`
        : columnLabel;

      cy.findAllByText(columnDisplayLabel).eq(-1).click();

      const expectedLabelCount = hasShortLabel ? 1 : 2;

      cy.findAllByText(columnDisplayLabel).should(
        'have.length',
        expectedLabelCount
      );

      if (hasShortLabel) {
        cy.findByText(columnDisplayLabel).should('be.visible');
      }

      cy.findByLabelText('Add columns').click();

      cy.makeSnapshot();
    });
  });
});

describe('Notification column', () => {
  it('displays notification column if the cloud notification feature is disabled', () => {
    store.set(
      platformFeaturesAtom,
      getPlatformFeatures({ notification: false })
    );
    interceptRequestsAndMountBeforeEach();

    cy.waitFiltersAndListingRequests();

    cy.findByTestId('Add columns').click();

    cy.findByText('Notification (Notif)').should('exist');
  });

  it('hides notification column if the cloud notification feature is enabled', () => {
    store.set(
      platformFeaturesAtom,
      getPlatformFeatures({ notification: true })
    );
    interceptRequestsAndMountBeforeEach();

    cy.waitFiltersAndListingRequests();

    cy.findByTestId('Add columns').click();

    cy.findByText('Severity (S)').should('exist');
    cy.findByText('Notification (Notif)').should('not.exist');

    cy.makeSnapshot();
  });
});

['light'].forEach((mode) => {
  describe(`Resource Listing: rows and picto colors on ${mode} theme`, () => {
    beforeEach(() => {
      store.set(userAtom, {
        themeMode: mode
      });

      store.set(selectedColumnIdsAtom, ['resource', 'state', 'information']);
      cy.interceptAPIRequest({
        alias: 'filterRequest',
        method: Method.GET,
        path: '**/events-view*',
        response: fakeData
      });

      cy.fixture('resources/listing/listingWithStates.json').then((data) => {
        cy.interceptAPIRequest({
          alias: 'listing',
          method: Method.GET,
          path: '**/resources?*',
          response: data
        });
      });
      cy.mount({
        Component: (
          <Router>
            <ListingTestWithJotai />
          </Router>
        )
      });
    });

    it('displays listing with state icons when the resource is in one or more states', () => {
      cy.waitForRequest('@filterRequest');
      cy.waitForRequest('@listing');
      cy.contains('Memory').should('be.visible');

      cy.findByRole('table').within(() => {
        cy.findAllByTestId('DowntimeIcon').should('have.length', 2);
        cy.findAllByTestId('PersonIcon').should('have.length', 2);
      });

      cy.findAllByTestId('FlappingIcon').should('have.length', 4);
      cy.findAllByTestId('FlappingIcon').first().trigger('mouseover');
      cy.contains(labelResourceFlapping).should('be.visible');
    });

    it('displays the listing row in downtime color when the resource is in downtime state', () => {
      cy.waitForRequest('@filterRequest');
      cy.waitForRequest('@listing');
      cy.contains('Memory').should('be.visible');

      cy.findAllByRole('row').should('have.length', 7);

      cy.findAllByRole('row')
        .eq(1)
        .children()
        .first()
        .should(
          'have.css',
          'background-color',
          equals(mode, 'dark') ? 'rgb(81, 41, 128)' : 'rgb(229, 216, 243)'
        );

      cy.makeSnapshot();
    });
    it('displays the listing row in acknowledge color when the resource is in acknowledge state but not in downtime', () => {
      cy.waitForRequest('@filterRequest');
      cy.waitForRequest('@listing');
      cy.contains('Memory').should('be.visible');

      cy.findAllByRole('row').should('have.length', 7);

      cy.findAllByRole('row')
        .eq(2)
        .children()
        .first()
        .should(
          'have.css',
          'background-color',
          equals(mode, 'dark') ? 'rgb(116, 95, 53)' : 'rgb(223, 210, 185)'
        );

      cy.makeSnapshot();
    });

    it('displays the listing row in flapping color when the resource is in flapping state only', () => {
      cy.waitForRequest('@filterRequest');
      cy.waitForRequest('@listing');
      cy.contains('Memory').should('be.visible');

      cy.findAllByRole('row').should('have.length', 7);

      cy.findAllByRole('row')
        .eq(4)
        .children()
        .first()
        .should(
          'have.css',
          'background-color',
          equals(mode, 'dark') ? 'rgb(6, 74, 63)' : 'rgb(216, 243, 239)'
        );

      cy.makeSnapshot();
    });

    it('displays a Down icon when the parent status is null', () => {
      cy.waitForRequest('@filterRequest');
      cy.waitForRequest('@listing');
      cy.contains('Memory').should('be.visible');

      cy.findByTestId('Add columns').click();
      cy.findByText('Parent').click();

      cy.contains(/^D$/).should('be.visible');

      cy.makeSnapshot();
    });
  });
});
