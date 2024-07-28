import { createStore, Provider } from 'jotai';
import { keys } from 'ramda';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import AdditionalConnectorConfiguration from '../Page';
import {
  additionalConnectorsEndpoint,
  getAdditionalConnectorEndpoint
} from '../api/endpoints';
import {
  labelAdditionalConnectorConfiguration,
  labelAddvCenterESX,
  labelCancel,
  labelCreate,
  labelCreateConnectorConfiguration,
  labelDescription,
  labelEditConnectorConfiguration,
  labelMoreFilters,
  labelName,
  labelParameters,
  labelPort,
  labelRemoovevCenterESX,
  labelSearch,
  labelSelectPollers,
  labelType,
  labelUpdate,
  labelUpdateConnectorConfiguration
} from '../translatedLabels';
import AdditionalConnectorModal from '../Modal/Modal';
import { dialogStateAtom } from '../atoms';
import { defaultParameters } from '../utils';
import { DialogState } from '../Listing/models';
import { ParameterKeys } from '../Modal/models';

const mockPageRequests = (): void => {
  cy.fixture('ACC/additionalConnectors.json').then((connectors) => {
    cy.interceptAPIRequest({
      alias: 'getConnectors',
      method: Method.GET,
      path: `${additionalConnectorsEndpoint}**`,
      response: connectors
    });
  });

  cy.fixture('ACC/additionalConnector.json').then((connector) => {
    cy.interceptAPIRequest({
      alias: 'getConnector',
      method: Method.GET,
      path: `${getAdditionalConnectorEndpoint(1)}**`,
      response: connector
    });
  });
};

const pageStore = createStore();

const initializePage = (): void => {
  mockPageRequests();
  cy.viewport(1200, 1000);

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={pageStore}>
            <AdditionalConnectorConfiguration />
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });
};

interface InitializeModal {
  variant?: 'create' | 'update';
}

const modalStore = createStore();

const initializeModal = ({ variant = 'create' }: InitializeModal): void => {
  const dialogState: DialogState = {
    connector: { id: 1 },
    isOpen: true,
    variant
  };

  modalStore.set(dialogStateAtom, dialogState);
  mockPageRequests();

  cy.viewport(1200, 1000);

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={modalStore}>
            <AdditionalConnectorModal />
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });
};

const vcenters = [
  {
    [ParameterKeys.name]: 'vCenter1',
    [ParameterKeys.password]: 'password1',
    [ParameterKeys.url]: 'https://vcenter1.example.com/sdk',
    [ParameterKeys.username]: 'user1'
  },
  {
    [ParameterKeys.name]: 'vCenter2',
    [ParameterKeys.password]: 'password2',
    [ParameterKeys.url]: '192.0.0.1',
    [ParameterKeys.username]: 'user2'
  }
];

describe('Additional Connctor Configuration', () => {
  describe('Page', () => {
    beforeEach(initializePage);
    it('displays the page title', () => {
      cy.waitForRequest('@getConnectors');

      cy.contains(labelAdditionalConnectorConfiguration);
    });
    it('displays Listing, filters and action buttons', () => {
      cy.waitForRequest('@getConnectors');

      cy.findByTestId('create-connector-configuration').should('be.visible');
      cy.findAllByTestId(labelSearch).first().should('be.visible');
      cy.findByTestId(labelMoreFilters).should('be.visible');

      cy.matchImageSnapshot();
    });
    it('opens the "Creation Modal" when "Add button" is clicked', () => {
      cy.waitForRequest('@getConnectors');

      cy.findByTestId('create-connector-configuration').click();

      cy.findByText(labelCreateConnectorConfiguration).should('be.visible');

      cy.matchImageSnapshot();

      cy.findByLabelText(labelCancel).click();
    });
    it('opens the "Edition Modal" when a row of the listing is clicked', () => {
      cy.waitForRequest('@getConnectors');

      cy.contains('VMWare1').click();

      cy.waitForRequest('@getConnectors');

      cy.findByText(labelUpdateConnectorConfiguration).should('be.visible');

      cy.matchImageSnapshot();

      cy.findByLabelText(labelCancel).click();
    });
    it('opens the "Edition Modal" when "Edit conncetor button" is clicked', () => {
      cy.waitForRequest('@getConnectors');

      cy.findAllByLabelText(labelEditConnectorConfiguration).first().click();

      cy.waitForRequest('@getConnectors');

      cy.findByText(labelUpdateConnectorConfiguration).should('be.visible');

      cy.findByLabelText(labelCancel).click();
    });
  });
  describe('Listing', () => {
    it('renders the listing component', () => {});
    it('displays all listing columns', () => {});
    it('displays listing actions', () => {});
    describe('Actions', () => {
      it('deletes an ACC when Delete Button is clicked and the confirmation button is triggered', () => {});
      it('does not delete an ACC when the corresponding button is clicked and the cancellation button is clicked', () => {});
      it('duplicate', () => {}); // ?
    });
    it('sorting', () => {});
    it('pagination', () => {});
  });
  describe('Filters', () => {
    it('render Filters', () => {
      it('displays the search bar component', () => {});
      it('displays the advanced filters component when the correspanding icon is clicked', () => {});
    });
  });
  describe('Modal', () => {
    it('displays form fields with default values when the Modal is opened in Creation Mode', () => {
      initializeModal({});

      cy.findByText(labelCreateConnectorConfiguration).should('be.visible');

      cy.findByLabelText(labelName)
        .should('be.visible')
        .should('have.value', '');

      cy.findByLabelText(labelDescription)
        .should('be.visible')
        .should('have.value', '');

      cy.findByTestId(labelType)
        .parent()
        .children()
        .first()
        .should('be.visible')
        .should('have.text', 'vmware_v6');

      cy.findByTestId(labelSelectPollers).should('be.visible');

      cy.findByText(labelParameters).should('be.visible');
      cy.findAllByTestId('parameterGroup').should('have.length', 1);

      keys(defaultParameters).forEach((parameter) => {
        cy.get(`input[data-testid="${parameter}"`)
          .should('be.visible')
          .should('have.value', parameter)
          .should('be.disabled');

        cy.get(`input[data-testid="${parameter}_value"`)
          .should('be.visible')
          .should('have.value', defaultParameters[parameter])
          .should('not.be.disabled');
      });

      cy.findByText(labelAddvCenterESX)
        .should('be.visible')
        .should('not.be.disabled');

      cy.get(`input[data-testid=${labelPort}`)
        .should('be.visible')
        .should('have.value', labelPort)
        .should('be.disabled');

      cy.get(`input[data-testid=${labelPort}_value`)
        .should('be.visible')
        .should('have.value', 5700)
        .should('not.be.disabled');

      cy.get(`button[data-testid="cancel"`)
        .should('be.visible')
        .should('have.text', labelCancel)
        .should('not.be.disabled');

      cy.get(`button[data-testid="submit"`)
        .should('be.visible')
        .should('have.text', labelCreate)
        .should('be.disabled');
    });
    it('displays form fileds with the selected ACC values when the Modal is opened in Edition Mode', () => {
      initializeModal({ variant: 'update' });

      cy.findByText(labelUpdateConnectorConfiguration).should('be.visible');

      cy.findByLabelText(labelName)
        .should('be.visible')
        .should('have.value', 'VMWare1');

      cy.findByLabelText(labelDescription)
        .should('be.visible')
        .should('have.value', 'Description for VMWare1');

      cy.findByTestId(labelType)
        .parent()
        .children()
        .first()
        .should('be.visible')
        .should('have.text', 'vmware_v6');

      cy.findByText('Poller1').should('be.visible');
      cy.findByText('Poller2').should('be.visible');

      cy.matchImageSnapshot();

      cy.findByTestId('Modal')
        .children()
        .eq(2)
        .children()
        .first()
        .scrollTo('bottom');

      cy.findByText(labelParameters).should('be.visible');
      cy.findAllByTestId('parameterGroup').should('have.length', 2);

      vcenters.forEach((vcenter, index) => {
        keys(vcenter).forEach((parameter) => {
          cy.get(`input[data-testid="${parameter}"`)
            .eq(index)
            .should('be.visible')
            .should('have.value', parameter)
            .should('be.disabled');

          cy.get(`input[data-testid="${parameter}_value"`)
            .eq(index)
            .should('be.visible')
            .should('have.value', vcenter[parameter])
            .should('not.be.disabled');
        });
      });

      cy.findByText(labelAddvCenterESX)
        .should('be.visible')
        .should('not.be.disabled');

      cy.get(`input[data-testid=${labelPort}`)
        .should('be.visible')
        .should('have.value', labelPort)
        .should('be.disabled');

      cy.get(`input[data-testid=${labelPort}_value`)
        .should('be.visible')
        .should('have.value', 443)
        .should('not.be.disabled');

      cy.get(`button[data-testid="cancel"`)
        .should('be.visible')
        .should('have.text', labelCancel)
        .should('not.be.disabled');

      cy.get(`button[data-testid="submit"`)
        .should('be.visible')
        .should('have.text', labelUpdate)
        .should('be.disabled');

      cy.matchImageSnapshot();
    });

    it('disables the update button when no change has been made to the modal form', () => {});
    it('disables the create/update button when there is error(s) in form field(s)', () => {});
    it('enable the create/update button when there is all mondatory fields are field', () => {});

    it('hides Delete Parameter Group button when there is only one paramter group', () => {});
    it('displays parameters group with four unchangable parameter names', () => {});
    it(`add new parameter group when the ${labelAddvCenterESX} button is clicked`, () => {});
    it(`removes a parameter group when the ${labelRemoovevCenterESX} button is clicked`, () => {});

    describe('Form validation', () => {
      it('name field is required', () => {});
      it('name length must be between 3 and 50 characters', () => {});
      it('description field is not required', () => {});
      it('description length must be less than 180', () => {});
      it('at least one poller is required', () => {});
      it('connector type field is required', () => {});
      it('port field is required', () => {});
      it('port should be a valid integer', () => {});
      it('port should be between 0 and 65535', () => {});

      it('vcenter name field is required', () => {});
      it('vcenter url field is required', () => {});
      it('vcenter url sould be a valid URL or an IP address', () => {});
      it('vcenter username field is required', () => {});
      it('vcenter password field is required', () => {});
    });
  });
});
