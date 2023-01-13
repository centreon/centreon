import { Provider, useAtomValue } from 'jotai';
import { renderHook } from '@testing-library/react-hooks/dom';

import { userAtom } from '@centreon/ui-context';
import { TestQueryProvider, Method } from '@centreon/ui';

import useFilter from '../../testUtils/useFilter';
import Context, { ResourceContext } from '../../testUtils/Context';
import {
  labelSaveFilter,
  labelSave,
  labelSaveAsNew,
  labelName
} from '../../translatedLabels';
import { Filter } from '../models';
import useListing from '../../Listing/useListing';
import { defaultSortField, defaultSortOrder } from '../Criterias/default';
import { getFilterWithUpdatedCriteria } from '../../testUtils';

import SaveMenu from '.';

let context;

const SaveMenuTest = (): JSX.Element => {
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
      <SaveMenu />
    </Context.Provider>
  );
};

const SaveMenuTestWithJotai = (): JSX.Element => (
  <TestQueryProvider>
    <Provider>
      <SaveMenuTest />
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

describe('SaveMenu', () => {
  beforeEach(() => {
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

    cy.mount({
      Component: <SaveMenuTestWithJotai />
    });
  });

  it('disables save menus when the current filter has no changes', () => {
    cy.waitForRequest('@getResourceRequest');
    cy.findByLabelText(labelSaveFilter).should('exist');
    cy.findByLabelText(labelSaveFilter).click();
    cy.findByText(labelSaveAsNew).should('have.attr', 'aria-disabled');
    cy.findByText(labelSave)?.parentElement?.parentElement.should(
      'have.attr',
      'aria-disabled'
    );

    cy.matchImageSnapshot();
  });

  it('sends an updateFilter request when the "Save" command is clicked', () => {
    cy.waitForRequest('@getResourceRequest');

    const filter = getFilter({});
    const newSearch = 'new search';
    const updatedFilter = getFilter({ search: newSearch });
    cy.interceptAPIRequest({
      alias: 'putFilterRequest',
      method: Method.PUT,
      path: '**filters/events-view**',
      response: updatedFilter
    });
    context.setCurrentFilter(
      getFilterWithUpdatedCriteria({
        criteriaName: 'search',
        criteriaValue: newSearch,
        filter
      })
    );

    cy.findByLabelText(labelSaveFilter).click();

    cy.findByText(labelSave).should('not.have.attr', 'aria-disabled');
    cy.findByText(labelSave).click();

    cy.waitForRequest('@putFilterRequest');
    cy.waitForRequest('@getResourceRequest');

    cy.matchImageSnapshot();
  });

  it('sends a createFilter request when the "Save as new" command is clicked', () => {
    cy.waitForRequest('@getResourceRequest');
    const filter = getFilter({});
    context.setCurrentFilter(
      getFilterWithUpdatedCriteria({
        criteriaName: 'search',
        criteriaValue: 'toto',
        filter
      })
    );

    cy.findByLabelText(labelSaveFilter).click();

    cy.findByText(labelSave)?.parentElement?.parentElement.should(
      'not.have.attr',
      'aria-disabled'
    );

    cy.findByText(labelSaveAsNew).click();

    cy.get('button[aria-label="Save"]').should('exist');
    cy.get('button[aria-label="Save"]').should('be.disabled');

    cy.findByLabelText(labelName).type('My new filter');

    cy.get('button[aria-label="Save"]').should('not.be.disabled');
    cy.get('button[aria-label="Save"]').click();

    cy.waitForRequest('@postFilterRequest');
    cy.waitForRequest('@getResourceRequest');

    cy.matchImageSnapshot();
  });
});
