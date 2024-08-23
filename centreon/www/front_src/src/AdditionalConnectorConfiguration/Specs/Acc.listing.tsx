import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import Listing from '../Listing/Listing';
import {
  additionalConnectorsEndpoint,
  getAdditionalConnectorEndpoint
} from '../api/endpoints';
import {
  labelAdditionalConnectorDeleted,
  labelCreationDate,
  labelCreator,
  labelDelete,
  labelDeleteAdditionalConnectorConfiguration,
  labelLastUpdate,
  labelName,
  labelSomeConnectorsMayNotWorkAnymore,
  labelType,
  labelUpdateBy
} from '../translatedLabels';

const mockRequests = (): void => {
  cy.fixture('ACC/additionalConnectors.json').then((connectors) => {
    cy.interceptAPIRequest({
      alias: 'getConnectors',
      method: Method.GET,
      path: `**${additionalConnectorsEndpoint}?**`,
      response: connectors
    });
  });

  cy.interceptAPIRequest({
    alias: 'deleteConnector',
    method: Method.DELETE,
    path: `**${getAdditionalConnectorEndpoint(1)}**`,
    response: {}
  });
};

const store = createStore();

const initializeListing = (): void => {
  mockRequests();

  cy.viewport(1200, 1000);

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={store}>
            <div style={{ height: '100vh' }}>
              <Listing />
            </div>
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });
};

const columnToSort = [
  { id: 'name', label: labelName },
  { id: 'type', label: labelType },
  { id: 'created_by', label: labelCreator },
  { id: 'created_at', label: labelCreationDate },
  { id: 'updated_by', label: labelUpdateBy },
  { id: 'updated_at', label: labelLastUpdate }
];

export default (): void => {
  describe('Listing', () => {
    it('displays the first page of the ACC listing', () => {
      initializeListing();
      cy.contains('VMWare1');
      cy.contains('Description for VMWare1');

      cy.matchImageSnapshot();
    });
    it('sends a listing request with the selected limit when the corresponding button is clicked', () => {
      initializeListing();

      cy.get('#Rows\\ per\\ page').click();
      cy.contains(/^20$/).click();

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(JSON.parse(request.url.searchParams.get('limit'))).to.equal(20);
      });
    });

    it('sends a listing request with the selected page when the corresponding button is clicked', () => {
      initializeListing();

      cy.findByLabelText('Next page').click();

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(JSON.parse(request.url.searchParams.get('page'))).to.equal(2);
      });
    });
    it('executes a listing request with sort parameter when a sortable column is clicked`', () => {
      initializeListing();

      columnToSort.forEach(({ label, id }) => {
        const sortBy = id;

        cy.findByLabelText(`Column ${label}`).click();

        cy.waitForRequest('@getConnectors').then(({ request }) => {
          expect(
            JSON.parse(request.url.searchParams.get('sort_by'))
          ).to.deep.equal({
            [sortBy]: 'desc'
          });
        });

        cy.matchImageSnapshot(
          `column sorting --  executes a listing request when the ${label} column is clicked`
        );
      });
    });
    it('deletes an ACC when the Delete button is clicked and the confirmation button is triggered', () => {
      initializeListing();

      cy.findAllByTestId(labelDelete).first().click();

      cy.contains(labelDeleteAdditionalConnectorConfiguration);
      cy.contains(labelSomeConnectorsMayNotWorkAnymore);

      cy.get(`button[data-testid="confirm"`).click();

      cy.waitForRequest('@deleteConnector');

      cy.contains(labelAdditionalConnectorDeleted);

      cy.matchImageSnapshot();
    });
  });
};
