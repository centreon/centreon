import { renderHook } from '@testing-library/react-hooks/dom';
import { Provider, useAtomValue } from 'jotai';

import { Method, TestQueryProvider } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import EditFiltersPanel from '..';
import useListing from '../../../Listing/useListing';
import Context, { ResourceContext } from '../../../testUtils/Context';
import useFilter from '../../../testUtils/useFilter';
import { labelDelete, labelEditFilters } from '../../../translatedLabels';
import { defaultSortField, defaultSortOrder } from '../../Criterias/default';
import { Filter } from '../../models';

import EditFilter from '.';

let context;

const EditFilterTest = (): JSX.Element => {
  const listingState = useListing();
  const filterState = useFilter();

  context = {
    ...listingState,
    ...filterState
  };

  return (
    <Context.Provider
      value={
        // eslint-disable-next-line react/jsx-no-constructed-context-values
        {
          ...context
        } as ResourceContext
      }
    >
      <EditFilter />
    </Context.Provider>
  );
};

const EditFilterTestWithJotai = (): JSX.Element => (
  <TestQueryProvider>
    <Provider>
      <div style={{ height: '100vh' }}>
        <EditFilterTest />
        <EditFiltersPanel />
      </div>
    </Provider>
  </TestQueryProvider>
);

const filterId = 0;

const getFilter = ({ search = 'my search', name = 'MyFilter' }): Filter => ({
  criterias: [
    {
      name: 'resource_types',
      object_type: null,
      type: 'multi_select',
      value: [
        {
          id: 'host',
          name: 'Host'
        }
      ]
    },
    {
      name: 'states',
      object_type: null,
      type: 'multi_select',
      value: [
        {
          id: 'unhandled_problems',
          name: 'Unhandled'
        }
      ]
    },
    {
      name: 'statuses',
      object_type: null,
      type: 'multi_select',
      value: [
        {
          id: 'OK',
          name: 'Ok'
        }
      ]
    },
    {
      name: 'host_groups',
      object_type: 'host_groups',
      type: 'multi_select',
      value: [
        {
          id: 0,
          name: 'Linux-servers'
        }
      ]
    },
    {
      name: 'service_groups',
      object_type: 'service_groups',
      type: 'multi_select',
      value: [
        {
          id: 0,
          name: 'Web-services'
        }
      ]
    },
    {
      name: 'monitoring_servers',
      object_type: 'monitoring_servers',
      type: 'multi_select',
      value: []
    },
    {
      name: 'host_categories',
      object_type: 'host_categories',
      type: 'multi_select',
      value: [
        {
          id: 0,
          name: 'Linux'
        }
      ]
    },
    {
      name: 'service_categories',
      object_type: 'service_categories',
      type: 'multi_select',
      value: [
        {
          id: 0,
          name: 'web-services'
        }
      ]
    },
    {
      name: 'search',
      object_type: null,
      type: 'text',
      value: search
    },
    {
      name: 'sort',
      object_type: null,
      type: 'array',
      value: [defaultSortField, defaultSortOrder]
    }
  ],
  id: filterId,
  name
});

const retrievedCustomFilters = {
  meta: {
    limit: 30,
    page: 1,
    total: 1
  },
  result: [getFilter({})]
};

before(() => {
  const userData = renderHook(() => useAtomValue(userAtom));

  userData.result.current.timezone = 'Europe/Paris';
  userData.result.current.locale = 'en_US';
});

describe('Edit filter button', () => {
  beforeEach(() => {
    cy.viewport('macbook-13');

    cy.interceptAPIRequest({
      alias: 'getResourceRequest',
      method: Method.GET,
      path: '**/events-view*',
      response: retrievedCustomFilters
    });

    cy.interceptAPIRequest({
      alias: 'putFilterRequest',
      method: Method.PUT,
      path: '**filters/events-view**',
      response: {}
    });

    cy.interceptAPIRequest({
      alias: 'postFilterRequest',
      method: Method.POST,
      path: '**filters/events-view',
      response: getFilter({})
    });

    cy.interceptAPIRequest({
      alias: 'deleteFilterRequest',
      method: Method.DELETE,
      path: '**filters/events-view**',
      statusCode: 204
    });

    cy.mount({
      Component: <EditFilterTestWithJotai />
    });
  });

  it('displays the filters in the edition panel', () => {
    cy.waitForRequest('@getResourceRequest');

    cy.findByLabelText(labelEditFilters).click();
  });

  it('sends a put request when the filter is updated', () => {
    cy.waitForRequest('@getResourceRequest');

    cy.findByLabelText(labelEditFilters).click();

    cy.get('input').type('updated');

    cy.contains(labelEditFilters).click();

    cy.waitForRequest('@putFilterRequest');
  });

  it('sends a delete request when the filter is delete', () => {
    cy.waitForRequest('@getResourceRequest');

    cy.findByLabelText(labelEditFilters).click();

    cy.get('input').type('updated');

    cy.findByLabelText(labelDelete).click();

    cy.findByLabelText(labelDelete).click();

    cy.waitForRequest('@deleteFilterRequest');
  });
});
