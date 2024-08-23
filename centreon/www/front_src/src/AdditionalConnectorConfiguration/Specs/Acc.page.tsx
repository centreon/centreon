import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import AdditionalConnectorConfiguration from '../Page';
import {
  additionalConnectorsEndpoint,
  getAdditionalConnectorEndpoint
} from '../api/endpoints';
import {
  labelAdditionalConnectorConfiguration,
  labelCancel,
  labelCreateConnectorConfiguration,
  labelEditConnectorConfiguration,
  labelMoreFilters,
  labelSearch,
  labelUpdateConnectorConfiguration
} from '../translatedLabels';

const mockPageRequests = (): void => {
  cy.fixture('ACC/additionalConnectors.json').then((connectors) => {
    cy.interceptAPIRequest({
      alias: 'getConnectors',
      method: Method.GET,
      path: `**${additionalConnectorsEndpoint}?**`,
      response: connectors
    });
  });

  cy.fixture('ACC/additionalConnector.json').then((connector) => {
    cy.interceptAPIRequest({
      alias: 'getConnector',
      method: Method.GET,
      path: `**${getAdditionalConnectorEndpoint(1)}**`,
      response: connector
    });
  });
};

const labelSomeConnectorsMayNotWorkAnymore = createStore();

const initializePage = (): void => {
  mockPageRequests();
  cy.viewport(1200, 1000);

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={labelSomeConnectorsMayNotWorkAnymore}>
            <AdditionalConnectorConfiguration />
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });
};

export default (): void => {
  describe('Page', () => {
    beforeEach(initializePage);
    it('displays the page title', () => {
      cy.waitForRequest('@getConnectors');

      cy.contains(labelAdditionalConnectorConfiguration);
    });
    it('displays listing, filters and action buttons', () => {
      cy.waitForRequest('@getConnectors');

      cy.findByTestId('create-connector-configuration').should('be.visible');
      cy.findAllByTestId(labelSearch).first().should('be.visible');
      cy.findByTestId(labelMoreFilters).should('be.visible');

      cy.matchImageSnapshot();
    });
    it('opens the modal in "Creation Mode" when "Add" button is clicked', () => {
      cy.waitForRequest('@getConnectors');

      cy.findByTestId('create-connector-configuration').click();

      cy.findByText(labelCreateConnectorConfiguration).should('be.visible');

      cy.matchImageSnapshot();

      cy.findByLabelText(labelCancel).click();
    });
    it('opens the modal in "Edition Mode" when a row of the listing is clicked', () => {
      cy.waitForRequest('@getConnectors');

      cy.contains('VMWare1').click();

      cy.waitForRequest('@getConnectors');

      cy.findByText(labelUpdateConnectorConfiguration).should('be.visible');

      cy.matchImageSnapshot();

      cy.findByLabelText(labelCancel).click();
    });
    it('opens the modal in "Edition Mode" when "Edit conncetor" button is clicked', () => {
      cy.waitForRequest('@getConnectors');

      cy.findAllByLabelText(labelEditConnectorConfiguration).first().click();

      cy.waitForRequest('@getConnectors');

      cy.findByText(labelUpdateConnectorConfiguration).should('be.visible');

      cy.findByLabelText(labelCancel).click();
    });
  });
};
