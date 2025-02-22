import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { initReactI18next } from 'react-i18next';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { platformFeaturesAtom } from '@centreon/ui-context';
import { capitalize } from '@mui/material';
import { equals } from 'ramda';
import { BrowserRouter as Router } from 'react-router';
import HostGroups from '..';
import {
  getHostGroupEndpoint,
  hostGroupsListEndpoint,
  hostListEndpoint
} from '../api/endpoints';
import {
  labelAlias,
  labelComment,
  labelDisabledHosts,
  labelEnabledHosts,
  labelGeographicCoordinates,
  labelInvalidCoordinateFormat,
  labelName,
  labelNoDisabledHosts,
  labelNoEnabledHosts
} from '../translatedLabels';
import {
  getDetailsResponse,
  getGroups,
  getListingResponse,
  getPayload,
  hostsListEmptyResponse
} from './utils';

const initialize = ({
  isEmptyHostGroup = false,
  isCloudPlatform = false
}): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();

  store.set(platformFeaturesAtom, {
    featureFlags: {},
    isCloudPlatform
  });

  cy.interceptAPIRequest({
    alias: 'getAllHostGroups',
    method: Method.GET,
    path: `**${hostGroupsListEndpoint}?**`,
    response: getListingResponse('host group')
  });

  cy.interceptAPIRequest({
    alias: 'getHostGroupDetails',
    method: Method.GET,
    path: `**${getHostGroupEndpoint({ id: 1 })}`,
    response: getDetailsResponse({ isCloudPlatform })
  });

  cy.interceptAPIRequest({
    alias: 'getHosts',
    method: Method.GET,
    path: `**${hostListEndpoint}?**`,
    response: isEmptyHostGroup
      ? hostsListEmptyResponse
      : getListingResponse('host')
  });

  cy.interceptAPIRequest({
    alias: 'updateHostGroup',
    method: Method.PUT,
    path: `**${getHostGroupEndpoint({ id: 1 })}`,
    response: {}
  });

  cy.interceptAPIRequest({
    alias: 'createHostGroup',
    method: Method.POST,
    path: `**${hostGroupsListEndpoint}`,
    response: getDetailsResponse({ isCloudPlatform })
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

    cy.makeSnapshot();
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

      cy.makeSnapshot();
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

      cy.makeSnapshot();
    });
  });

  it('displays and interacts with filters', () => {
    initialize({});

    cy.waitForRequest('@getAllHostGroups');

    cy.get('[data-testid="search-bar"]').should('be.visible');

    cy.get(`[data-testid="Filters"]`).eq(1).click();

    cy.get('[data-testid="advanced-filters"]').should('be.visible');

    cy.contains(labelName).should('be.visible');
    cy.contains(labelAlias).should('be.visible');

    cy.makeSnapshot();
  });

  describe('Modal', () => {
    beforeEach(() => initialize({}));

    it('shows form fields organized into groups, with each field initialized with default values', () => {
      cy.waitForRequest('@getAllHostGroups');

      cy.get(`[data-testid="add-resource"]`).click();

      getGroups({}).forEach(({ name }) => {
        cy.contains(name);
      });

      cy.findAllByTestId(labelName).eq(1).should('have.value', '');
      cy.findAllByTestId(labelAlias).eq(1).should('have.value', '');
      cy.findByText('host 1').should('not.exist');
      cy.findByText('host 2').should('not.exist');
      cy.findByText('host 3').should('not.exist');
      cy.findAllByTestId(labelGeographicCoordinates)
        .eq(1)
        .should('have.value', '');
      cy.findAllByTestId(labelComment).eq(1).should('have.value', '');

      cy.makeSnapshot();

      cy.findByLabelText('close').click();
    });

    it('shows form fields organized into groups, with each field initialized with the value received from the API', () => {
      cy.waitForRequest('@getAllHostGroups');

      cy.contains('host group 1').click();

      cy.waitForRequest('@getHostGroupDetails');

      getGroups({}).forEach(({ name }) => {
        cy.contains(name);
      });

      cy.findAllByTestId(labelName)
        .eq(1)
        .should('have.value', getPayload({}).name);
      cy.findAllByTestId(labelAlias)
        .eq(1)
        .should('have.value', getPayload({}).alias);
      cy.findByText('host 1').should('be.visible');
      cy.findByText('host 2').should('be.visible');
      cy.findByText('host 3').should('be.visible');
      cy.findAllByTestId(labelGeographicCoordinates)
        .eq(1)
        .should('have.value', getPayload({}).geo_coords);
      cy.findAllByTestId(labelComment)
        .eq(1)
        .should('have.value', getPayload({}).comment);

      cy.makeSnapshot();

      cy.findByLabelText('close').click();
    });

    it('sends a POST request when the Create Button is clicked', () => {
      cy.waitForRequest('@getAllHostGroups');

      cy.get(`[data-testid="add-resource"]`).click();

      cy.findAllByTestId(labelName).eq(1).clear().type(getPayload({}).name);
      cy.findAllByTestId(labelAlias).eq(1).clear().type(getPayload({}).alias);
      cy.findAllByTestId(labelComment)
        .eq(1)
        .clear()
        .type(getPayload({}).comment);
      cy.findAllByTestId(labelGeographicCoordinates)
        .eq(1)
        .clear()
        .type(getPayload({}).geo_coords);

      cy.get(`button[data-testid="submit"`).click();

      cy.waitForRequest('@createHostGroup').then(({ request }) => {
        expect(request.body).to.deep.equals({ ...getPayload({}), hosts: [] });
      });

      cy.makeSnapshot();
    });

    it('sends an UPDATE request when the Update Button is clicked', () => {
      cy.waitForRequest('@getAllHostGroups');

      cy.contains('host group 1').click();

      cy.waitForRequest('@getHostGroupDetails');

      cy.findAllByTestId(labelName).eq(1).clear().type('Updated name');

      cy.get(`button[data-testid="submit"`).click();

      cy.waitForRequest('@updateHostGroup').then(({ request }) => {
        expect(request.body).to.deep.equals({
          ...getPayload({}),
          name: 'Updated name'
        });
      });

      cy.contains('Host group updated');

      cy.makeSnapshot();
    });

    it('validate geographic coordianates', () => {
      cy.waitForRequest('@getAllHostGroups');

      cy.makeSnapshot();

      cy.get(`[data-testid="add-resource"]`).click();

      cy.findAllByTestId(labelName).eq(1).clear().type('name');
      cy.findAllByTestId(labelGeographicCoordinates).eq(1).clear().type('123');
      cy.findByTestId('Modal-header').click();

      cy.contains(labelInvalidCoordinateFormat);
      cy.get(`button[data-testid="submit"`).should('be.disabled');

      cy.makeSnapshot();

      cy.findAllByTestId(labelGeographicCoordinates)
        .eq(1)
        .clear()
        .type('-40.12,22.44');

      cy.findByText(labelInvalidCoordinateFormat).should('not.exist');
      cy.get(`button[data-testid="submit"`).should('not.be.disabled');

      cy.makeSnapshot();
    });
  });
});
