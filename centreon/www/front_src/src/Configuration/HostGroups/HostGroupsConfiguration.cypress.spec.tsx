import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { initReactI18next } from 'react-i18next';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { capitalize } from '@mui/material';
import { equals } from 'ramda';
import { BrowserRouter as Router } from 'react-router';
import HostGroups from '.';
import { hostGroupsListEndpoint, hostListEndpoint } from './api/endpoints';
import {
  labelAlias,
  labelDisabledHosts,
  labelEnabledHosts,
  labelName,
  labelNoDisabledHosts,
  labelNoEnabledHosts
} from './translatedLabels';

const getListingResponse = (resourceType) => ({
  result: Array.from({ length: 8 }, (_, i) => ({
    id: i,
    name: equals(i, 5) ? 'hostGroup0'.repeat(20) : `${resourceType} ${i}`,
    alias: equals(i, 5)
      ? 'alias'.repeat(20)
      : `alias for  ${resourceType} ${i}`,
    enabled_hosts_count: i % 2 ? 0 : 3 * i,
    disabled_hosts_count: i % 2 ? 5 * i : 0,
    is_activated: !!(i % 2),
    icon: null
  })),
  meta: {
    limit: 10,
    page: 1,
    total: 8
  }
});

const hostsListEmptyResponse = {
  result: [],
  meta: {
    limit: 10,
    page: 1,
    total: 0
  }
};

const initialize = ({ isEmptyHostGroup = false }): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();

  cy.interceptAPIRequest({
    alias: 'getAllHostGroups',
    method: Method.GET,
    path: `**${hostGroupsListEndpoint}?**`,
    response: getListingResponse('host group')
  });

  cy.interceptAPIRequest({
    alias: 'getHosts',
    method: Method.GET,
    path: `**${hostListEndpoint}?**`,
    response: isEmptyHostGroup
      ? hostsListEmptyResponse
      : getListingResponse('host')
  });

  cy.mount({
    Component: (
      <Router>
        <SnackbarProvider>
          <TestQueryProvider>
            <Provider store={store}>
              <HostGroups />
            </Provider>
          </TestQueryProvider>
        </SnackbarProvider>
      </Router>
    )
  });
};

describe('Host groups configuration: ', () => {
  it('renders the Host group page with the ConfigurationBase layout', () => {
    initialize({});

    cy.waitForRequest('@getAllHostGroups');

    cy.contains('Host groups').should('be.visible');

    cy.matchImageSnapshot();
  });

  ['name', 'alias'].forEach((column) => {
    it(`sorts the ${column} column when clicked`, () => {
      initialize({});

      cy.waitForRequest('@getAllHostGroups');

      cy.contains(capitalize(column)).click();

      cy.waitForRequest('@getAllHostGroups').then(({ request }) => {
        expect(
          JSON.parse(request.url.searchParams.get('sort_by'))
        ).to.deep.equal({
          [column]: 'desc'
        });
      });
    });
  });

  it('truncates the name and alias fields when their length exceeds 50 characters', () => {
    initialize({});

    cy.contains(`${'hostGroup0'.repeat(5)}...`).should('be.visible');
    cy.contains(`${'alias'.repeat(10)}...`).should('be.visible');
  });

  ['enabled host groups', 'disabled host groups'].forEach((column, i) => {
    const isEnabledHost = equals(i, 0);

    it(`displays all hosts of the host group when hovering over the ${column} column`, () => {
      initialize({});

      cy.waitForRequest('@getAllHostGroups');

      cy.findByText(isEnabledHost ? '12' : '15').trigger('mouseover');

      cy.contains(
        isEnabledHost ? labelEnabledHosts : labelDisabledHosts
      ).should('be.visible');
      cy.contains('host 1').should('be.visible');

      cy.matchImageSnapshot();
    });

    it(`displays a 'Not found' message when hovering over the ${column} column with no hosts`, () => {
      initialize({ isEmptyHostGroup: true });

      cy.waitForRequest('@getAllHostGroups');

      cy.findAllByText('0')
        .eq(isEnabledHost ? 6 : 7)
        .trigger('mouseover');

      cy.contains(
        isEnabledHost ? labelNoEnabledHosts : labelNoDisabledHosts
      ).should('be.visible');

      cy.matchImageSnapshot();
    });
  });

  it('displays and interacts with filters', () => {
    initialize({});

    cy.waitForRequest('@getAllHostGroups');

    cy.get('[data-testid="search-bar"]').should('be.visible');

    cy.get(`[data-testid="Filters"]`).click();

    cy.get('[data-testid="advanced-filters"]').should('be.visible');

    cy.contains(labelName).should('be.visible');
    cy.contains(labelAlias).should('be.visible');

    cy.matchImageSnapshot();
  });
});
