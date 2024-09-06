import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import Filters from '../Listing/ActionsBar/Filters/Filters';
import {
  additionalConnectorsEndpoint,
  pollersEndpoint
} from '../api/endpoints';
import {
  labelClear,
  labelMoreFilters,
  labelName,
  labelPollers,
  labelSearch,
  labelTypes
} from '../translatedLabels';

const store = createStore();

const mockRequests = (): void => {
  cy.fixture('ACC/additionalConnectors.json').then((connectors) => {
    cy.interceptAPIRequest({
      alias: 'getConnectors',
      method: Method.GET,
      path: `**${additionalConnectorsEndpoint}?**`,
      response: connectors
    });
  });

  cy.fixture('ACC/pollers-vmware.json').then((pollers) => {
    cy.interceptAPIRequest({
      alias: 'gePollers',
      method: Method.GET,
      path: `**${pollersEndpoint}**`,
      response: pollers
    });
  });
};

const initializeFilter = (): void => {
  mockRequests();

  cy.viewport(1200, 1000);

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={store}>
            <Filters />
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });
};

export default (): void => {
  describe('Filters', () => {
    beforeEach(initializeFilter);
    it('displays the search bar component', () => {
      cy.get(`input[data-testid="${labelSearch}"`).should('be.visible');

      cy.matchImageSnapshot();
    });
    it('displays the advanced filters component when the corresponding icon is clicked', () => {
      cy.findByTestId(labelMoreFilters).click();

      cy.get(`input[data-testid="${labelName}"`).should('be.visible');
      cy.findByTestId(labelPollers).should('be.visible');
      cy.findByTestId(labelTypes).should('be.visible');

      cy.get(`button[data-testid="${labelSearch}"`).should('be.visible');
      cy.get(`button[data-testid="${labelClear}"`).should('be.visible');

      cy.matchImageSnapshot();
    });
    it('updates the filters with the value from the search bar', () => {
      cy.get(`input[data-testid="${labelSearch}"`)
        .clear()
        .type('vmware1 types:VMWare_6/7 pollers:poller1,poller2');

      cy.findByTestId(labelMoreFilters).click();

      cy.get(`input[data-testid="${labelName}"`).should(
        'have.value',
        'vmware1'
      );

      cy.findByTestId(labelTypes)
        .parent()
        .within(() => {
          cy.contains('VMWare_6/7');
        });

      cy.findByTestId(labelPollers)
        .parent()
        .within(() => {
          cy.contains('poller1');
          cy.contains('poller2');
        });
    });
    it('updates the search bar with the value from the filters', () => {
      cy.get(`input[data-testid="${labelSearch}"`).clear();

      cy.findByTestId(labelMoreFilters).click();

      cy.get(`input[data-testid="${labelName}"`).type('vmware1');

      cy.findByTestId(labelTypes).click();
      cy.contains('VMWare_6/7').click();
      cy.findByTestId(labelTypes).click();

      cy.findByTestId(labelPollers).click();
      cy.contains('poller1').click();
      cy.contains('poller2').click();
      cy.findByTestId(labelPollers).click();

      cy.get(`input[data-testid="${labelSearch}"`).should(
        'have.value',
        'name:vmware1 types:VMWare_6/7 pollers:poller1,poller2'
      );

      cy.matchImageSnapshot();
    });
    it('sends a listing request with selected filters when the "Enter" key is pressed', () => {
      cy.get(`input[data-testid="${labelSearch}"`)
        .clear()
        .type('vmware1 types:VMWare_6/7 pollers:poller1,poller2')
        .type('{enter}');

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(
          JSON.parse(request.url.searchParams.get('search'))
        ).to.deep.equal({
          $and: [
            {
              $or: [
                { 'poller.name': { $rg: 'poller1' } },
                { 'poller.name': { $rg: 'poller2' } }
              ]
            },
            { $or: [{ type: { $rg: 'vmware_v6' } }] },
            { $or: [{ name: { $rg: 'vmware1' } }] }
          ]
        });
      });
    });
    it('sends a listing request with selected filters when the search button is clicked', () => {
      cy.get(`input[data-testid="${labelSearch}"`).clear();

      cy.findByTestId(labelMoreFilters).click();

      cy.get(`input[data-testid="${labelName}"`).type('vmware1');

      cy.findByTestId(labelTypes).click();
      cy.contains('VMWare_6/7').click();
      cy.findByTestId(labelTypes).click();

      cy.findByTestId(labelPollers).click();
      cy.contains('poller1').click();
      cy.contains('poller2').click();
      cy.findByTestId(labelPollers).click();

      cy.get(`button[data-testid="${labelSearch}"`).click();

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(
          JSON.parse(request.url.searchParams.get('search'))
        ).to.deep.equal({
          $and: [
            {
              $or: [
                { 'poller.name': { $rg: 'poller1' } },
                { 'poller.name': { $rg: 'poller2' } }
              ]
            },
            { $or: [{ type: { $rg: 'vmware_v6' } }] },
            { $or: [{ name: { $rg: 'vmware1' } }] }
          ]
        });
      });
    });

    it('clears filters and the search bar, and sends a listing request with empty search parameter when the clear button is clicked', () => {
      cy.get(`input[data-testid="${labelSearch}"`)
        .clear()
        .type('vmware1 types:VMWare_6/7 pollers:poller1,poller2');

      cy.findByTestId(labelMoreFilters).click();

      cy.get(`button[data-testid="${labelClear}"`).click();

      cy.get(`input[data-testid="${labelSearch}"`).should('have.value', '');

      cy.get(`input[data-testid="${labelName}"`).should('have.value', '');

      cy.findByTestId(labelTypes)
        .parent()
        .within(() => {
          cy.findByText('VMWare_6/7').should('not.exist');
        });

      cy.findByTestId(labelPollers)
        .parent()
        .within(() => {
          cy.findByText('poller1').should('not.exist');
          cy.findByText('poller2').should('not.exist');
        });

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(
          JSON.parse(request.url.searchParams.get('search'))
        ).to.deep.equal({
          $and: []
        });
      });

      cy.matchImageSnapshot();
    });
  });
};
