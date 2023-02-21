/* eslint-disable @typescript-eslint/no-unused-expressions */
import { useAtomValue } from 'jotai';
import { renderHook } from '@testing-library/react-hooks/dom';
import * as Ramda from 'ramda';

import { TestQueryProvider, Method } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import {
  labelSearch,
  labelResourceProblems,
  labelAll,
  labelSearchOptions,
  labelType,
  labelHost,
  labelState,
  labelAcknowledged,
  labelStatus,
  labelOk,
  labelStatusType,
  labelStateFilter,
  labelSoft,
  labelHostGroup,
  labelServiceGroup
} from '../translatedLabels';
import useListing from '../Listing/useListing';
import useLoadResources from '../Listing/useLoadResources';
import {
  defaultStatuses,
  getListingEndpoint,
  searchableFields,
  EndpointParams
} from '../testUtils';

import useFilter from './useFilter';

import Filter from '.';

const emptyListData = {
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 0 },
  result: []
};

const linuxServersHostGroup = {
  id: 0,
  name: 'Linux-servers'
};

const FirewallHostGroup = {
  id: 1,
  name: 'Firewall'
};

const webAccessServiceGroup = {
  id: 0,
  name: 'Web-access'
};

const filterParams = [
  {
    criteria: labelType,
    endpointParam: { resourceTypes: ['host'] },
    value: labelHost
  },
  {
    criteria: labelState,
    endpointParam: { states: ['acknowledged'] },
    value: labelAcknowledged
  },
  {
    criteria: labelStatus,
    endpointParam: { statuses: ['OK'] },
    value: labelOk
  },
  {
    criteria: labelStatusType,
    endpointParam: { statusTypes: ['soft'] },
    value: labelSoft
  },
  {
    criteria: labelHostGroup,
    endpointParam: { hostGroups: [linuxServersHostGroup.name] },
    value: linuxServersHostGroup.name
  },
  {
    criteria: labelServiceGroup,
    endpointParam: { serviceGroups: [webAccessServiceGroup.name] },
    value: webAccessServiceGroup.name
  }
];

const customFilters = [
  [
    labelAll,
    {
      resourceTypes: [],
      states: [],
      statusTypes: [],
      statuses: []
    }
  ],
  [
    labelResourceProblems,
    {
      resourceTypes: [],
      states: [],
      statusTypes: [],
      statuses: defaultStatuses
    }
  ]
];

const FilterWithLoading = (): JSX.Element => {
  useLoadResources();

  return <Filter />;
};

const FilterTest = (): JSX.Element | null => {
  useFilter();
  useListing();

  return <FilterWithLoading />;
};

const FilterWithProvider = (): JSX.Element => (
  <TestQueryProvider>
    <FilterTest />
  </TestQueryProvider>
);

before(() => {
  const userData = renderHook(() => useAtomValue(userAtom));

  userData.result.current.timezone = 'Europe/Paris';
  userData.result.current.locale = 'en_US';
});

describe('Filter', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'filterRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: emptyListData
    });

    const DefaultEndpoint = getListingEndpoint({});

    cy.interceptAPIRequest({
      alias: `defaultRequest`,
      method: Method.GET,
      path: Ramda.replace('./api/latest/monitoring', '**', DefaultEndpoint),
      response: emptyListData
    });

    const searchValue = 'foobar';

    const endpointWithSearchValue = getListingEndpoint({
      resourceTypes: [],
      search: searchValue,
      states: [],
      statusTypes: [],
      statuses: []
    });

    cy.interceptAPIRequest({
      alias: `getListRequest`,
      method: Method.GET,
      path: Ramda.replace(
        './api/latest/monitoring',
        '**',
        endpointWithSearchValue
      ),
      response: emptyListData
    });

    searchableFields.forEach((searchableField) => {
      const search = 'foobar';
      const fieldSearchValue = `${searchableField}:${search}`;
      const endpoint = getListingEndpoint({
        resourceTypes: [],
        search: fieldSearchValue,
        states: [],
        statusTypes: [],
        statuses: []
      });
      cy.interceptAPIRequest({
        alias: `request/${searchableField}`,
        method: Method.GET,
        path: Ramda.replace('./api/latest/monitoring', '**', endpoint)
      });
    });

    cy.mount({
      Component: <FilterWithProvider />
    });

    cy.viewport(1200, 1000);
  });

  it('executes a listing request with "Unhandled problems" filter by default', () => {
    cy.waitForRequest('@defaultRequest');

    cy.matchImageSnapshot();
  });

  searchableFields.forEach((searchableField) => {
    it(`executes a listing request with an "$and" search param containing ${searchableField} when ${searchableField} is typed in the search field`, () => {
      cy.waitForRequest('@filterRequest');

      const search = 'foobar';
      const fieldSearchValue = `${searchableField}:${search}`;

      cy.findByPlaceholderText(labelSearch).clear();
      cy.findByPlaceholderText(labelSearch).type(fieldSearchValue);
      cy.findByLabelText(labelSearchOptions).click();
      cy.findByText(labelSearch).click();

      cy.waitForRequest(`@request/${searchableField}`);

      cy.matchImageSnapshot();
    });
  });

  it('executes a listing request with an "$or" search param containing all searchable fields when a string that does not correspond to any searchable field is typed in the search field', () => {
    const searchValue = 'foobar';

    const searchableFieldExpressions = searchableFields.map(
      (searchableField) => `{"${searchableField}":{"$rg":"${searchValue}"}}`
    );

    cy.findByPlaceholderText(labelSearch).clear();

    cy.findByPlaceholderText(labelSearch).type(searchValue);

    cy.findByLabelText(labelSearchOptions).click();

    cy.findByText(labelSearch).click();

    cy.waitForRequest('@getListRequest').then(({ request }) => {
      // eslint-disable-next-line @typescript-eslint/no-unused-expressions
      expect(
        Ramda.includes(
          `search={"$or":[${searchableFieldExpressions}]}`,
          decodeURIComponent(request.url.search)
        )
      ).to.be.true;
    });

    cy.matchImageSnapshot();
  });
});

describe('Custom filters', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'filterRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: emptyListData
    });

    cy.interceptAPIRequest({
      alias: 'hostgroupsRequest',
      method: Method.GET,
      path: '**/hostgroups?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [linuxServersHostGroup]
      }
    });

    cy.interceptAPIRequest({
      alias: 'serviceGroupsRequest',
      method: Method.GET,
      path: '**/servicegroups?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [webAccessServiceGroup]
      }
    });

    filterParams.forEach(({ criteria, endpointParam }) => {
      const searchValue = 'foobar';

      const endpoint = getListingEndpoint({
        resourceTypes: [],
        search: searchValue,
        states: [],
        statusTypes: [],
        statuses: [],
        ...endpointParam
      });

      cy.interceptAPIRequest({
        alias: `request/${criteria}`,
        method: Method.GET,
        path: Ramda.replace('./api/latest/monitoring', '**', endpoint)
      });
    });

    cy.mount({
      Component: <FilterWithProvider />
    });

    cy.viewport(1200, 1000);

    customFilters.forEach(([filterGroup, criterias]) => {
      const endpoint = getListingEndpoint(criterias as EndpointParams);

      cy.interceptAPIRequest({
        alias: `request/${filterGroup}`,
        method: Method.GET,
        path: Ramda.replace('./api/latest/monitoring', '**', endpoint)
      });
    });
  });

  customFilters.forEach(([filterGroup, criterias]) => {
    it(`executes a listing request with ${filterGroup} parameters when ${JSON.stringify(
      criterias
    )} filter is set`, () => {
      cy.waitForRequest('@filterRequest');

      cy.findByLabelText(labelStateFilter).click();

      cy.findByText(filterGroup).click();

      cy.waitForRequest(`@request/${filterGroup}`);
    });
  });

  filterParams.forEach(({ criteria, value }) => {
    it(`executes a listing request with current search and selected ${criteria} criteria when it's changed`, () => {
      const searchValue = 'foobar';

      cy.findByPlaceholderText(labelSearch).clear();

      cy.findByPlaceholderText(labelSearch).type(searchValue);

      cy.findByLabelText(labelSearchOptions).click();

      cy.findByText(criteria).click();

      cy.findByText(value).click();

      cy.findByText(labelSearch).click();

      cy.waitForRequest(`@request/${criteria}`);

      cy.matchImageSnapshot();
    });
  });
});

describe('Keyboard actions', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'filterRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: emptyListData
    });

    cy.interceptAPIRequest({
      alias: 'hostgroupsRequest',
      method: Method.GET,
      path: '**/hostgroups?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [linuxServersHostGroup, FirewallHostGroup]
      }
    });

    cy.interceptAPIRequest({
      alias: 'serviceGroupsRequest',
      method: Method.GET,
      path: '**/servicegroups?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [webAccessServiceGroup]
      }
    });

    cy.mount({
      Component: <FilterWithProvider />
    });

    cy.viewport(1200, 1000);
  });

  it('accepts the selected autocomplete suggestion when the beginning of a criteria is input and the "enter" key is pressed', () => {
    const searchBar = cy.findByPlaceholderText(labelSearch);

    searchBar.clear();

    searchBar.type('stat').type('{enter}');
    searchBar.should('have.value', 'state:');

    searchBar.type('u').type('{enter}');

    searchBar.should('have.value', 'state:unhandled');

    searchBar.type(' st').type('{enter}');

    searchBar.should('have.value', 'state:unhandled status:');

    searchBar.type(' type:');
    searchBar.type('{downArrow}').type('{enter}');

    searchBar.should('have.value', 'state:unhandled status: type:service');

    cy.matchImageSnapshot();
  });

  it(`accepts the selected autocomplete suggestion when the beginning of a dynamic criteria is input and the "enter" key is pressed`, () => {
    const searchBar = cy.findByPlaceholderText(labelSearch);

    searchBar.clear();
    searchBar.type('host');
    searchBar.type('{Enter}');
    searchBar.should('have.value', 'host_group:');
    searchBar.type('ESX');
    cy.findByText(linuxServersHostGroup.name).should('exist');
    searchBar.type('{Enter}');
    cy.findByPlaceholderText(labelSearch).should(
      'have.value',
      `host_group:${linuxServersHostGroup.name}`
    );

    searchBar.type(',');
    cy.findByText('Firewall').should('exist');
    searchBar.type('{downArrow}');
    searchBar.type('{Enter}');
    cy.waitForRequest('@hostgroupsRequest');

    cy.matchImageSnapshot();
  });
});
