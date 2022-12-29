/* eslint-disable no-underscore-dangle */
/* eslint-disable @typescript-eslint/no-unused-expressions */
import * as React from 'react';

import * as Ramda from 'ramda';
import { BrowserRouter as Router } from 'react-router-dom';
import { renderHook } from '@testing-library/react-hooks/dom';
import { useAtomValue } from 'jotai';

import { Method, TestQueryProvider } from '@centreon/ui';
import type { Column } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { Resource, ResourceType } from '../models';
import { labelInDowntime, labelAcknowledged } from '../translatedLabels';
import { getListingEndpoint, defaultSecondSortCriteria } from '../testUtils';
import useDetails from '../Details/useDetails';
import { resourcesToAcknowledgeAtom } from '../Actions/actionsAtoms';
import useFilter from '../Filter/useFilter';

import { getColumns, defaultSelectedColumnIds } from './columns';
import useLoadDetails from './useLoadResources/useLoadDetails';

import Listing from '.';

const columns = getColumns({
  actions: {
    resourcesToAcknowledgeAtom
  },
  t: Ramda.identity
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

const ListingTest = (): JSX.Element => {
  useLoadDetails();
  useFilter();
  useDetails();

  return <Listing />;
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
  const userData = renderHook(() => useAtomValue(userAtom));

  userData.result.current.timezone = 'Europe/Paris';
  userData.result.current.locale = 'en_US';
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
    const [resourcesWithMultipleLines, resourcesWithSingleLines] =
      Ramda.partition(
        Ramda.where({ information: Ramda.includes('\n') }),
        retrievedListing.result
      );
    cy.waitFiltersAndListingRequests();

    resourcesWithMultipleLines.forEach(({ information }) =>
      cy
        .contains(
          Ramda.pipe(
            Ramda.split('\n'),
            Ramda.head
          )(information as string) as string
        )
        .should('exist')
    );
    resourcesWithSingleLines.forEach(({ information }) => {
      cy.contains(information as string).should('exist');
    });

    cy.matchImageSnapshot();
  });
});

describe('column sorting', () => {
  const columnToSort = columns
    .filter(({ sortable }) => sortable !== false)
    .filter(({ id }) => Ramda.includes(id, defaultSelectedColumnIds));

  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'filterRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: fakeData
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

    columnToSort.forEach(({ id, label, sortField }) => {
      const sortBy = (sortField || id) as string;
      const secondSortCriteria =
        Ramda.not(Ramda.equals(sortField, 'last_status_change')) &&
        defaultSecondSortCriteria;

      const requestUrlDesc = getListingEndpoint({
        sort: {
          [sortBy]: 'desc',
          ...secondSortCriteria
        }
      });

      cy.interceptAPIRequest({
        alias: `dataToListingTableDesc${label}`,
        method: Method.GET,
        path: Ramda.replace('./api/latest/monitoring', '**', requestUrlDesc),
        response: retrievedListing
      });

      const requestUrlAsc = getListingEndpoint({
        sort: {
          [sortBy]: 'asc',
          ...secondSortCriteria
        }
      });
      cy.interceptAPIRequest({
        alias: `dataToListingTableAsc${label}`,
        method: Method.GET,
        path: Ramda.replace('./api/latest/monitoring', '**', requestUrlAsc),
        response: retrievedListing
      });
    });
  });

  columnToSort.forEach(({ label }) => {
    it(`executes a listing request with sort_by param and stores the order parameter in the URL when ${label} column is clicked`, () => {
      cy.waitForRequest('@filterRequest');

      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequest(`@dataToListingTableDesc${label}`);

      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequest(`@dataToListingTableAsc${label}`);

      cy.matchImageSnapshot();
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
      .should((label) => {
        expect(label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      expect(Ramda.includes('page=2&limit=30', request.url.search)).to.be.true;
    });

    cy.findByLabelText(`Previous page`)
      .should((label) => {
        expect(label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      expect(Ramda.includes('page=1&limit=30', request.url.search)).to.be.true;
    });

    cy.findByLabelText(`Last page`)
      .should((label) => {
        expect(label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      expect(Ramda.includes('page=4&limit=30', request.url.search)).to.be.true;
    });

    cy.findByLabelText(`First page`)
      .should((label) => {
        expect(label).to.be.enabled;
      })
      .click();

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      expect(Ramda.includes('page=1&limit=30', request.url.search)).to.be.true;
    });

    cy.matchImageSnapshot();
  });

  it('executes a listing request with a limit param when the rows per page value is changed', () => {
    cy.waitFiltersAndListingRequests();

    cy.get('#Rows\\ per\\ page').click();
    cy.contains(/^30$/).click({ force: true });

    cy.waitForRequest('@dataToListingTable').then(({ request }) => {
      expect(Ramda.includes('&limit=30', request.url.search)).to.be.true;
    });

    cy.matchImageSnapshot();
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

    const entityInDowntime = entities.find(({ in_downtime }) => in_downtime);

    const chipLabel = `${entityInDowntime?.name} ${labelInDowntime}`;

    cy.findByLabelText(chipLabel, {
      timeout: 10000
    }).trigger('mouseover');

    cy.waitForRequest('@downtimeRequest').then(({ request }) => {
      expect(
        Ramda.includes(
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

    cy.matchImageSnapshot();
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
        Ramda.includes(
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

    cy.matchImageSnapshot();
  });

  const columnIds = Ramda.map(Ramda.prop('id'), columns);

  const additionalIds = Ramda.reject(
    Ramda.includes(Ramda.__, defaultSelectedColumnIds),
    columnIds
  );

  additionalIds.forEach((columnId) => {
    it(`displays additional columns when selected from the corresponding ${columnId} menu`, () => {
      cy.waitFiltersAndListingRequests();

      cy.findByLabelText('Add columns').click();

      const column = Ramda.find(Ramda.propEq('id', columnId), columns);
      const columnLabel = column?.label as string;

      const columnShortLabel = column?.shortLabel as string;

      const hasShortLabel = !Ramda.isNil(columnShortLabel);

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

      cy.matchImageSnapshot();
    });
  });
});
