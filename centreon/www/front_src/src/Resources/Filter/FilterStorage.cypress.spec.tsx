import { useAtomValue } from 'jotai';
import { renderHook } from '@testing-library/react-hooks/dom';
import * as Ramda from 'ramda';

import { TestQueryProvider, Method } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import {
  labelSearch,
  labelUnhandledProblems,
  labelAll,
  labelSearchOptions,
  labelType,
  labelHost,
  labelState,
  labelAcknowledged,
  labelStatus,
  labelOk,
  labelStateFilter,
  labelHostGroup,
  labelServiceGroup,
  labelNewFilter
} from '../translatedLabels';
import useListing from '../Listing/useListing';
import useLoadResources from '../Listing/useLoadResources';
import { getListingEndpoint, getFilterWithUpdatedCriteria } from '../testUtils';

import { allFilter } from './models';
import useFilter from './useFilter';
import { filterKey } from './filterAtoms';
import { defaultSortField, defaultSortOrder } from './Criterias/default';

import Filter from '.';

const emptyListData = {
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

const filter = {
  criterias: [
    {
      name: 'resource_types',
      value: [{ id: 'host', name: labelHost }]
    },
    {
      name: 'states',
      value: [{ id: 'acknowledged', name: labelAcknowledged }]
    },
    { name: 'statuses', value: [{ id: 'OK', name: labelOk }] },
    {
      name: 'host_groups',
      object_type: 'host_groups',
      value: [linuxServersHostGroup]
    },
    {
      name: 'service_groups',
      object_type: 'service_groups',
      value: [webAccessServiceGroup]
    },
    { name: 'search', value: 'Search me' },
    { name: 'sort', value: [defaultSortField, defaultSortOrder] }
  ],
  id: 0,
  name: 'My filter'
};

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

describe('Filter storage', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'filterRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: emptyListData
    });

    const getAllEndpoint = getListingEndpoint({
      resourceTypes: [],
      states: [],
      statusTypes: [],
      statuses: []
    });

    cy.interceptAPIRequest({
      alias: 'getAllrequest',
      method: Method.GET,
      path: Ramda.replace('./api/latest/monitoring', '**', getAllEndpoint)
    });

    cy.interceptAPIRequest({
      alias: 'hostGroupsRequest',
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

    cy.mount({
      Component: <FilterWithProvider />
    });
    localStorage.setItem(filterKey, JSON.stringify(filter));

    cy.viewport(1200, 1000);
  });

  it('populates filter with values from localStorage if available', () => {
    cy.waitForRequest('@filterRequest');

    cy.findByLabelText(labelUnhandledProblems).should('not.exist');

    const searchField = cy.findByPlaceholderText(labelSearch);

    searchField.should(
      'have.value',
      'type:host state:acknowledged status:ok host_group:Linux-servers service_group:Web-access Search me'
    );

    cy.findByLabelText(labelSearchOptions).click();

    cy.findByText(labelType).click();
    cy.findByText(labelHost).should('exist');
    cy.findByText(labelType).click();

    cy.findByText(labelState).click();
    cy.findByText(labelAcknowledged).should('exist');
    cy.findByText(labelState).click();

    cy.findByText(labelStatus).click();
    cy.findByText(labelOk).should('exist');
    cy.findByText(labelStatus).click();

    cy.findByText(labelHostGroup).click();
    cy.waitForRequest('@hostGroupsRequest');
    const linuxServerOption = cy.findByText(linuxServersHostGroup.name);
    linuxServerOption.should('exist');
    cy.findByText(labelHostGroup).click();

    cy.findByText(labelServiceGroup).click();
    cy.waitForRequest('@serviceGroupsRequest');
    cy.findByText(webAccessServiceGroup.name).should('exist');

    cy.matchImageSnapshot();
  });

  it('stores filter values in localStorage when updated', () => {
    cy.waitForRequest('@filterRequest');
    cy.findByLabelText(labelStateFilter).click();

    cy.findByText(labelAll).click();

    const searchField = cy.findByPlaceholderText(labelSearch);
    searchField.clear();
    searchField.type('searching...');

    cy.waitForRequest('@getAllrequest').then(() => {
      expect(localStorage.getItem(filterKey)).to.deep.equal(
        JSON.stringify(
          getFilterWithUpdatedCriteria({
            criteriaName: 'search',
            criteriaValue: 'searching...',
            filter: { ...allFilter, id: '', name: labelNewFilter }
          })
        )
      );
    });

    cy.matchImageSnapshot();
  });
});
