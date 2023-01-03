/* eslint-disable @typescript-eslint/no-unused-expressions */
/* eslint-disable react/jsx-no-constructed-context-values */
import { useAtomValue } from 'jotai';
import { renderHook } from '@testing-library/react-hooks/dom';
import * as Ramda from 'ramda';

import { TestQueryProvider, Method } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import {
  labelSearch,
  labelResourceProblems,
  labelAll,
  labelUnhandledProblems,
  labelSearchOptions,
  labelType,
  labelHost,
  labelState,
  labelAcknowledged,
  labelStatus,
  labelOk,
  labelStatusType,
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

const fakeData = {
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 0 },
  result: []
};

const linuxServersHostGroup = {
  id: 0,
  name: 'Linux-servers'
};

const webAccessServiceGroup = {
  id: 0,
  name: 'Web-access'
};

type FilterParameter = [string, string, Record<string, unknown>];

const filterParams: Array<FilterParameter> = [
  [labelType, labelHost, { resourceTypes: ['host'] }],
  [
    labelState,
    labelAcknowledged,
    {
      states: ['acknowledged']
    }
  ],
  [
    labelStatus,
    labelOk,
    {
      statuses: ['OK']
    }
  ],
  [
    labelStatusType,
    labelSoft,
    {
      statusTypes: ['soft']
    }
  ],
  [
    labelHostGroup,
    linuxServersHostGroup.name,
    {
      hostGroups: [linuxServersHostGroup.name]
    }
  ],
  [
    labelServiceGroup,
    webAccessServiceGroup.name,
    {
      serviceGroups: [webAccessServiceGroup.name]
    }
  ]
];

const customFilter = [
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
      response: fakeData
    });

    const DefaultEndpoint = getListingEndpoint({});

    cy.interceptAPIRequest({
      alias: `defaultRequest`,
      method: Method.GET,
      path: Ramda.replace('./api/latest/monitoring', '**', DefaultEndpoint),
      response: fakeData
    });

    cy.mount({
      Component: <FilterWithProvider />
    });

    cy.viewport(1200, 1000);

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

    const endpoint = getListingEndpoint({
      resourceTypes: [],
      search: searchValue,
      states: [],
      statusTypes: [],
      statuses: []
    });

    cy.interceptAPIRequest({
      alias: `getListRequest`,
      method: Method.GET,
      path: Ramda.replace('./api/latest/monitoring', '**', endpoint),
      response: fakeData
    });
    const searchableFieldExpressions = searchableFields.map(
      (searchableField) => `{"${searchableField}":{"$rg":"${searchValue}"}}`
    );

    cy.findByPlaceholderText(labelSearch).clear();

    cy.findByPlaceholderText(labelSearch).type(searchValue);

    cy.findByLabelText(labelSearchOptions).click();

    cy.findByText(labelSearch).click();

    cy.waitForRequest('@getListRequest').then(({ request }) => {
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

describe('Custom filter', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'filterRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: fakeData
    });

    cy.interceptAPIRequest({
      alias: 'hostgroups',
      method: Method.GET,
      path: '**/hostgroups?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [linuxServersHostGroup]
      }
    });

    cy.interceptAPIRequest({
      alias: 'dataToListingTable',
      method: Method.GET,
      path: '**/servicegroups?*',
      response: {
        meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
        result: [webAccessServiceGroup]
      }
    });

    filterParams.forEach(([criteriaName, _, endpointParamChanged]) => {
      const searchValue = 'foobar';

      const endpoint = getListingEndpoint({
        resourceTypes: [],
        search: searchValue,
        states: [],
        statusTypes: [],
        statuses: [],
        ...endpointParamChanged
      });

      cy.interceptAPIRequest({
        alias: `request/${criteriaName}`,
        method: Method.GET,
        path: Ramda.replace('./api/latest/monitoring', '**', endpoint)
      });
    });

    cy.mount({
      Component: <FilterWithProvider />
    });

    cy.viewport(1200, 1000);

    customFilter.forEach(([filterGroup, criterias]) => {
      const endpoint = getListingEndpoint(criterias as EndpointParams);

      cy.interceptAPIRequest({
        alias: `request/${filterGroup}`,
        method: Method.GET,
        path: Ramda.replace('./api/latest/monitoring', '**', endpoint)
      });
    });
  });
  customFilter.forEach(([filterGroup, criterias]) => {
    it(`executes a listing request with ${filterGroup} parameters when ${JSON.stringify(
      criterias
    )} filter is set`, () => {
      cy.findByText(labelUnhandledProblems).click();

      cy.findByText(filterGroup).click();

      cy.waitForRequest(`@request/${filterGroup}`);
    });
  });

  filterParams.forEach(([criteriaName, optionToSelect, _]) => {
    it(`executes a listing request with current search and selected ${criteriaName} criteria when it's changed`, () => {
      const searchValue = 'foobar';

      cy.findByPlaceholderText(labelSearch).clear();

      cy.findByPlaceholderText(labelSearch).type(searchValue);

      cy.findByLabelText(labelSearchOptions).click();

      cy.findByText(criteriaName).click();

      cy.findByText(optionToSelect).click();

      cy.findByText(labelSearch).click();

      cy.waitForRequest(`@request/${criteriaName}`);

      cy.matchImageSnapshot();
    });
  });
});
