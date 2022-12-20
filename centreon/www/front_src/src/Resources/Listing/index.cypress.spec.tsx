/* eslint-disable @typescript-eslint/no-unused-expressions */
import * as React from 'react';

import {
  partition,
  where,
  includes,
  head,
  split,
  pipe,
  identity,
  prop,
  reject,
  map,
  __,
  propEq,
  find,
  isNil,
  equals,
  not
} from 'ramda';
import { BrowserRouter as Router } from 'react-router-dom';

import { cyan } from '@mui/material/colors';

import { Column, Method, TestQueryProvider } from '@centreon/ui';

import { Resource, ResourceType } from '../models';
import Context, { ResourceContext } from '../testUtils/Context';
import useActions from '../testUtils/useActions';
import useFilter from '../testUtils/useFilter';
import { labelInDowntime, labelAcknowledged } from '../translatedLabels';
import { getListingEndpoint, defaultSecondSortCriteria } from '../testUtils';
import useLoadDetails from '../testUtils/useLoadDetails';
import useDetails from '../Details/useDetails';
import { resourcesToAcknowledgeAtom } from '../Actions/actionsAtoms';

import useListing from './useListing';
import { getColumns, defaultSelectedColumnIds } from './columns';

import Listing from '.';

const columns = getColumns({
  actions: {
    resourcesToAcknowledgeAtom
  },
  t: identity
}) as Array<Column>;

const fillEntities = (): Array<Resource> => {
  const entityCount = 31;

  return new Array(entityCount).fill(0).map((_, index) => ({
    acknowledged: index % 2 === 0,
    duration: '1m',
    id: index,
    in_downtime: index % 3 === 0,
    information:
      index % 5 === 0 ? `Entity ${index}` : `Entity ${index}\n Line ${index}`,
    last_check: '1m',
    links: {
      endpoints: {
        acknowledgement: `/monitoring/acknowledgement/${index}`,
        details: 'endpoint',
        downtime: `/monitoring/downtime/${index}`,
        metrics: 'endpoint',
        performance_graph: index % 6 === 0 ? 'endpoint' : undefined,
        status_graph: index % 3 === 0 ? 'endpoint' : undefined,
        timeline: 'endpoint'
      },
      externals: {
        notes: {
          url: 'https://centreon.com'
        }
      },
      uris: {
        configuration: index % 7 === 0 ? 'uri' : undefined,
        logs: index % 4 === 0 ? 'uri' : undefined,
        reporting: index % 3 === 0 ? 'uri' : undefined
      }
    },
    name: `E${index}`,
    passive_checks: index % 8 === 0,
    severity_level: index % 3 === 0 ? 1 : 2,
    short_type: index % 4 === 0 ? 's' : 'h',
    status: {
      name: index % 2 === 0 ? 'OK' : 'PENDING',
      severity_code: index % 2 === 0 ? 2 : 5
    },
    tries: '1',
    type: index % 4 === 0 ? ResourceType.service : ResourceType.host,
    uuid: `${index}`
  }));
};

const entities = fillEntities();
const retrievedListing = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: entities.length
  },
  result: entities
};

let context: ResourceContext;

const ListingTest = (): JSX.Element => {
  const listingState = useListing();
  const actionsState = useActions();
  const detailsState = useLoadDetails();
  const filterState = useFilter();

  useDetails();

  context = {
    ...listingState,
    ...actionsState,
    ...detailsState,
    ...filterState
  };

  return (
    <Context.Provider value={context}>
      <Listing />
    </Context.Provider>
  );
};

const ListingTestWithJotai = (): JSX.Element => (
  <TestQueryProvider>
    <ListingTest />
  </TestQueryProvider>
);
const fakeData = {
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 0 },
  result: []
};

before(() => {
  document.getElementsByTagName('body')[0].style = 'margin:0px';
});

const interceptRequestsAndMountBeforeEach = (): void => {
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
    response: retrievedListing
  });
  cy.mount({
    Component: (
      <Router>
        <div style={{ backgroundColor: '#fff' }}>
          <ListingTestWithJotai />
        </div>
      </Router>
    )
  });

  cy.viewport(1200, 1000);
};

describe('Resource Listing', () => {
  beforeEach(() => {
    interceptRequestsAndMountBeforeEach();
  });

  it('displays first part of information when multiple (split by \n) are available', () => {
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

    // cy.matchImageSnapshot();
  });
});

describe('column sorting', () => {
  beforeEach(() => {
    // interceptRequestsAndMountBeforeEach();
    cy.mount({
      Component: (
        <Router>
          <div style={{ backgroundColor: '#fff' }}>
            <ListingTestWithJotai />
          </div>
        </Router>
      )
    });

    cy.viewport(1200, 1000);
  });

  const columnToClick = columns
    .filter(({ sortable }) => sortable !== false)
    .filter(({ id }) => includes(id, defaultSelectedColumnIds));

  columnToClick.forEach(({ id, label, sortField }) => {
    it(`executes a listing request with sort_by param and stores the order parameter in the URL when ${label} column is clicked`, () => {
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
        response: retrievedListing
      });
      cy.waitFiltersAndListingRequests();

      const sortBy = (sortField || id) as string;
      const secondSortCriteria =
        not(equals(sortField, 'last_status_change')) &&
        defaultSecondSortCriteria;

      const requestUrlDesc = getListingEndpoint({
        sort: {
          [sortBy]: 'desc',
          ...secondSortCriteria
        }
      });
      cy.log('requestDesc', requestUrlDesc);
      let resultDesc;
      cy.findByLabelText(`Column ${label}`).should('be.visible').click();

      cy.waitForRequest('@dataToListingTable').then(({ request }) => {
        resultDesc = request.url.search;
      });
      cyan.log('resultDesc', resultDesc);
      expect(includes(resultDesc, requestUrlDesc)).to.be.true;

      const requestUrlAsc = getListingEndpoint({
        sort: {
          [sortBy]: 'asc',
          ...secondSortCriteria
        }
      });
      cy.log('requestAsc', requestUrlAsc);

      let resultAsc;
      cy.findByLabelText(`Column ${label}`).should('be.visible').click();

      cy.waitForRequest('@dataToListingTable').then(({ request }) => {
        resultAsc = request.url.search;
      });
      expect(includes(resultAsc, requestUrlAsc)).to.be.true;
      // cy.matchImageSnapshot();
    });
  });
});

describe('Listing request', () => {
  beforeEach(() => {
    interceptRequestsAndMountBeforeEach();
  });

  it('executes a listing request with an updated page param when a change page action is clicked', () => {
    cy.waitFiltersAndListingRequests();

    cy.findByLabelText(`Next page`)
      .should(($label) => {
        expect($label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      cy.log('request', request.url.search);
      // const requestUrlPageTwo = getListingEndpoint({ page: 2 });
      expect(includes('page=2&limit=30', request.url.search)).to.be.true;
    });

    cy.findByLabelText(`Previous page`)
      .should(($label) => {
        expect($label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      // const requestUrlPageOne = getListingEndpoint({ page: 1 });
      cy.log('request', request.url.search);
      expect(includes('page=1&limit=30', request.url.search)).to.be.true;
    });

    cy.findByLabelText(`Last page`)
      .should(($label) => {
        expect($label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      // const requestUrlPageOne = getListingEndpoint({ page: 4 });
      cy.log('request', request.url.search);
      expect(includes('page=4&limit=30', request.url.search)).to.be.true;
    });

    cy.findByLabelText(`First page`)
      .should(($label) => {
        expect($label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      // const requestUrlPageOne = getListingEndpoint({ page: 1 });
      cy.log('request', request.url.search);
      expect(includes('page=1&limit=30', request.url.search)).to.be.true;
    });
    // cy.matchImageSnapshot();
  });

  it('executes a listing request with a limit param when the rows per page value is changed', () => {
    cy.waitFiltersAndListingRequests();

    cy.get('#Rows\\ per\\ page').click();
    cy.contains(/^30$/).click({ force: true });

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      // const requestUrlLimit = getListingEndpoint({ limit: 30 });
      cy.log('request', request.url.search);
      expect(includes('&limit=30', request.url.search)).to.be.true;
    });
    // cy.matchImageSnapshot();
  });
});

describe('Details display', () => {
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

    const entityInDowntime = entities.find(({ in_downtime }) => in_downtime);

    const chipLabel = `${entityInDowntime?.name} ${labelInDowntime}`;

    cy.findByLabelText(chipLabel, {
      timeout: 10000
    }).trigger('mouseover');

    cy.waitForRequest('@downtimeRequest').then(({ request }) => {
      expect(
        includes(
          request.url.pathname,
          entityInDowntime?.links?.endpoints.downtime as string
        )
      ).to.be.true;
    });

    cy.findByText('admin').should('exist');
    cy.findByText('Yes').should('exist');
    cy.findByText('02/28/2020 9:16 AM').should('exist');
    cy.findByText('02/28/2020 9:18 AM').should('exist');
    cy.findByText('Set by admin').should('exist');

    // cy.matchImageSnapshot();
  });

  it('displays acknowledgement details when an acknowledged state chip is hovered', () => {
    cy.waitFiltersAndListingRequests();

    const acknowledgedEntity = entities.find(
      ({ acknowledged }) => acknowledged
    );

    const chipLabel = `${acknowledgedEntity?.name} ${labelAcknowledged}`;

    cy.findByLabelText(chipLabel, {
      timeout: 10000
    }).trigger('mouseover');

    cy.waitForRequest('@acknowledgeRequest').then(({ request }) => {
      expect(
        includes(
          request.url.pathname,
          acknowledgedEntity?.links?.endpoints.acknowledgement as string
        )
      ).to.be.true;
    });

    cy.findByText('admin').should('exist');
    cy.findByText('Yes').should('exist');
    cy.findByText('02/28/2020 9:16 AM').should('exist');
    cy.findByText('No').should('exist');
    cy.findByText('Set by admin').should('exist');

    // cy.matchImageSnapshot();
  });

  const columnIds = map(prop('id'), columns);

  const additionalIds = reject(
    includes(__, defaultSelectedColumnIds),
    columnIds
  );

  additionalIds.forEach((columnId) => {
    it(`displays additional columns when selected from the corresponding ${columnId} menu`, () => {
      cy.waitFiltersAndListingRequests();

      cy.findByLabelText('Add columns').click();

      const column = find(propEq('id', columnId), columns);
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
        cy.findByText(columnDisplayLabel).should('exist');
      }

      // cy.matchImageSnapshot();
    });
  });
});
