import { createStore, Provider } from 'jotai';
import { keys } from 'ramda';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import AdditionalConnectorConfiguration from '../Page';
import {
  additionalConnectorsEndpoint,
  getAdditionalConnectorEndpoint,
  getPollersForConnectorTypeEndpoint,
  pollersEndpoint
} from '../api/endpoints';
import {
  labelAdditionalConnectorConfiguration,
  labelAdditionalConnectorCreated,
  labelAdditionalConnectorUpdated,
  labelAddvCenterESX,
  labelAteastOnePollerIsRequired,
  labelCancel,
  labelClear,
  labelCreate,
  labelCreateConnectorConfiguration,
  labelDescription,
  labelEditConnectorConfiguration,
  labelInvalidPortNumber,
  labelMoreFilters,
  labelMustBeAvalidURL,
  labelName,
  labelNameMustBeAtLeast,
  labelParameters,
  labelPollers,
  labelPort,
  labelRemoveVCenterESX,
  labelRequired,
  labelSearch,
  labelSelectPollers,
  labelType,
  labelTypes,
  labelUpdate,
  labelUpdateConnectorConfiguration
} from '../translatedLabels';
import AdditionalConnectorModal from '../Modal/Modal';
import { dialogStateAtom } from '../atoms';
import { defaultParameters } from '../utils';
import { DialogState } from '../Listing/models';
import { ParameterKeys } from '../Modal/models';
import Filters from '../Listing/ActionsBar/Filters/Filters';
import Listing from '../Listing/Listing';

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

  cy.fixture('ACC/pollers-vmware.json').then((pollers) => {
    cy.interceptAPIRequest({
      alias: 'geAllowedPollers',
      method: Method.GET,
      path: `**${getPollersForConnectorTypeEndpoint({})}**`,
      response: pollers
    });
    cy.interceptAPIRequest({
      alias: 'geAllowedPollers',
      method: Method.GET,
      path: `**${pollersEndpoint}**`,
      response: pollers
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

const initializeFilter = (): void => {
  mockPageRequests();

  cy.viewport(1200, 1000);

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={modalStore}>
            <Filters />
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });
};

const initializeListing = (): void => {
  mockPageRequests();

  cy.viewport(1200, 1000);

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={modalStore}>
            <Listing />
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
    it('displays the first page of the ACC listing', () => {
      initializeListing();
      cy.contains('VMWare1');
      cy.contains('Description for VMWare1');

      cy.matchImageSnapshot();
    });
    it('sends a new listing request whith a new limit param when the corrresponding button was clicked', () => {
      initializeListing();

      cy.get('#Rows\\ per\\ page').click();
      cy.contains(/^20$/).click();

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(JSON.parse(request.url.searchParams.get('limit'))).to.equal(20);
      });
    });

    it('sends a new listing request whith a new page param when the corrresponding button was clicked', () => {
      initializeListing();

      cy.findByLabelText('Next page').click();

      cy.waitForRequest('@getConnectors').then(({ request }) => {
        expect(JSON.parse(request.url.searchParams.get('page'))).to.equal(2);
      });
    });
    it('displays all listing columns', () => {});
    it('displays listing actions', () => {});
    describe('Actions', () => {
      it('deletes an ACC when Delete Button is clicked and the confirmation button is triggered', () => {});
      it('does not delete an ACC when the corresponding button is clicked and the cancellation button is clicked', () => {});
      it('duplicate', () => {}); // ?
    });
    it('sorting', () => {});
  });
  describe('Filters', () => {
    beforeEach(initializeFilter);
    it('displays the search bar component', () => {
      cy.get(`input[data-testid="${labelSearch}"`).should('be.visible');

      cy.matchImageSnapshot();
    });
    it('displays the advanced filters component when the correspanding icon is clicked', () => {
      cy.findByTestId(labelMoreFilters).click();

      cy.get(`input[data-testid="${labelName}"`).should('be.visible');
      cy.findByTestId(labelPollers).should('be.visible');
      cy.findByTestId(labelTypes).should('be.visible');

      cy.get(`button[data-testid="${labelSearch}"`).should('be.visible');
      cy.get(`button[data-testid="${labelClear}"`).should('be.visible');

      cy.matchImageSnapshot();
    });
    it('updates the filters with the value of the search bar', () => {
      cy.get(`input[data-testid="${labelSearch}"`)
        .clear()
        .type('vmware1 types:vmware_v6 pollers:poller1,poller2');

      cy.findByTestId(labelMoreFilters).click();

      cy.get(`input[data-testid="${labelName}"`).should(
        'have.value',
        'vmware1'
      );

      cy.findByTestId(labelTypes)
        .parent()
        .within(() => {
          cy.contains('vmware_v6');
        });

      cy.findByTestId(labelPollers)
        .parent()
        .within(() => {
          cy.contains('poller1');
          cy.contains('poller2');
        });
    });
    it('update the search bar with the value of filters ', () => {
      cy.get(`input[data-testid="${labelSearch}"`).clear();

      cy.findByTestId(labelMoreFilters).click();

      cy.get(`input[data-testid="${labelName}"`).type('vmware1');

      cy.findByTestId(labelTypes).click();
      cy.contains('vmware_v6').click();
      cy.findByTestId(labelTypes).click();

      cy.findByTestId(labelPollers).click();
      cy.contains('poller1').click();
      cy.contains('poller2').click();
      cy.findByTestId(labelPollers).click();

      cy.get(`input[data-testid="${labelSearch}"`).should(
        'have.value',
        'name:vmware1 types:vmware_v6 pollers:poller1,poller2'
      );

      cy.matchImageSnapshot();
    });
    it('sends a lising request with selected filters when the enter key was pressed', () => {
      cy.get(`input[data-testid="${labelSearch}"`)
        .clear()
        .type('vmware1 types:vmware_v6 pollers:poller1,poller2')
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
    it('sends a lising request with selected filters when the search button was clicked', () => {
      cy.get(`input[data-testid="${labelSearch}"`).clear();

      cy.findByTestId(labelMoreFilters).click();

      cy.get(`input[data-testid="${labelName}"`).type('vmware1');

      cy.findByTestId(labelTypes).click();
      cy.contains('vmware_v6').click();
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

    it('clear filters and search bar and send a listing request without search values when the clear button was clicked', () => {
      cy.get(`input[data-testid="${labelSearch}"`)
        .clear()
        .type('vmware1 types:vmware_v6 pollers:poller1,poller2');

      cy.findByTestId(labelMoreFilters).click();

      cy.get(`button[data-testid="${labelClear}"`).click();

      cy.get(`input[data-testid="${labelSearch}"`).should('have.value', '');

      cy.get(`input[data-testid="${labelName}"`).should('have.value', '');

      cy.findByTestId(labelTypes)
        .parent()
        .within(() => {
          cy.findByText('vmware_v6').should('not.exist');
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
        ).to.deep.equal({ $and: [] });
      });

      cy.matchImageSnapshot();
    });
  });
  describe('Modal', () => {
    it('displays form fields with default values when the Modal is opened in Creation Mode', () => {
      initializeModal({});

      cy.findByText(labelCreateConnectorConfiguration).should('be.visible');

      cy.findAllByTestId(labelName)
        .eq(1)
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

      cy.findAllByTestId(labelName)
        .eq(1)
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

    it('disables the update button when no change has been made to the modal form', () => {
      initializeModal({ variant: 'update' });

      cy.findByText(labelUpdateConnectorConfiguration).should('be.visible');

      cy.findByTestId('Modal')
        .children()
        .eq(2)
        .children()
        .first()
        .scrollTo('bottom');

      cy.get(`button[data-testid="submit"`)
        .should('be.visible')
        .should('have.text', labelUpdate)
        .should('be.disabled');

      cy.matchImageSnapshot();
    });
    it('disables the create/update button when there is error(s) in form field(s)', () => {
      initializeModal({ variant: 'update' });

      cy.findByText(labelUpdateConnectorConfiguration).should('be.visible');
      cy.findAllByTestId(labelName).eq(1).clear();

      cy.findByTestId('Modal')
        .children()
        .eq(2)
        .children()
        .first()
        .scrollTo('bottom');

      cy.get(`button[data-testid="submit"`)
        .should('be.visible')
        .should('have.text', labelUpdate)
        .should('be.disabled');
    });
    it('enables the create/update button when all mondatory fields are field', () => {
      initializeModal({ variant: 'create' });

      cy.findByText(labelCreateConnectorConfiguration).should('be.visible');

      cy.get(`button[data-testid="submit"`)
        .should('have.text', labelCreate)
        .should('be.disabled');

      cy.findAllByTestId(labelName).eq(1).type('New name');

      cy.findByTestId(labelSelectPollers).click();

      cy.contains('poller1').click();

      cy.findByTestId(labelSelectPollers).click();

      cy.get(`input[data-testid="URL_value"`)
        .clear()
        .type('http://10.10.10.10/sdk');

      cy.get(`input[data-testid="Username_value"`).type('username');
      cy.get(`input[data-testid="Password_value"`).type('password');

      cy.get(`button[data-testid="submit"`)
        .should('be.visible')
        .should('have.text', labelCreate)
        .should('not.be.disabled');

      cy.matchImageSnapshot();
    });

    it('hides Delete Parameter Group buttons when there is only one paramter group', () => {
      initializeModal({ variant: 'create' });

      cy.findByTestId(labelRemoveVCenterESX).should('not.exist');
    });
    it(`add new parameter group when the ${labelAddvCenterESX} button is clicked`, () => {
      initializeModal({ variant: 'create' });

      cy.findAllByTestId('parameterGroup').should('have.length', 1);

      cy.findByText(labelAddvCenterESX).click();

      cy.findByTestId('Modal')
        .children()
        .eq(2)
        .children()
        .first()
        .scrollTo('bottom');

      cy.findAllByTestId('parameterGroup').should('have.length', 2);

      cy.matchImageSnapshot();
    });
    it(`removes a parameter group when the ${labelRemoveVCenterESX} button is clicked`, () => {
      initializeModal({ variant: 'create' });

      cy.findByText(labelAddvCenterESX).click();

      cy.findByTestId('Modal')
        .children()
        .eq(2)
        .children()
        .first()
        .scrollTo('bottom');

      cy.findAllByTestId(labelRemoveVCenterESX).should('have.length', 2);

      cy.findAllByTestId('parameterGroup').should('have.length', 2);

      cy.findAllByTestId(labelRemoveVCenterESX).first().click();

      cy.findAllByTestId('parameterGroup').should('have.length', 1);
    });

    describe('Form validation', () => {
      it('name field is required', () => {
        initializeModal({ variant: 'create' });

        cy.findAllByTestId(labelName).eq(1).clear();

        cy.get('body').click(0, 0);

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();
      });

      it('at least one poller is required', () => {
        initializeModal({ variant: 'create' });

        cy.findByTestId(labelSelectPollers).click();

        cy.contains('poller1').click();
        cy.get('body').click(0, 0);

        cy.findByTestId('CancelIcon').click();
        cy.get('body').click(0, 0);

        cy.contains(labelAteastOnePollerIsRequired).should('be.visible');

        cy.matchImageSnapshot();
      });

      it(`vcenter name field is required`, () => {
        initializeModal({ variant: 'create' });

        cy.get(`input[data-testid="Vcenter name_value"`).clear();

        cy.get('body').click(0, 0);

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();
      });

      it(`vcenter URL field is required`, () => {
        initializeModal({ variant: 'create' });

        cy.get(`input[data-testid="URL_value"`).clear();

        cy.get('body').click(0, 0);

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();
      });

      it(`vcenter username is required in Creation Mode`, () => {
        initializeModal({ variant: 'create' });

        cy.get(`input[data-testid="Username_value"`).clear();

        cy.get('body').click(0, 0);

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();
      });

      it(`vcenter username is not required Edition Mode`, () => {
        initializeModal({ variant: 'update' });

        cy.get(`input[data-testid="Username_value"`).clear();

        cy.get('body').click(0, 0);

        cy.contains(labelRequired).should('not.exist');

        cy.matchImageSnapshot();
      });

      it(`vcenter password field is required in Creation Mode`, () => {
        initializeModal({ variant: 'create' });

        cy.get(`input[data-testid="Password_value"`).clear();

        cy.get('body').click(0, 0);

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();
      });

      it(`vcenter password field is not required in Edition Mode`, () => {
        initializeModal({ variant: 'update' });

        cy.get(`input[data-testid="Password_value"`).clear();

        cy.get('body').click(0, 0);

        cy.contains(labelRequired).should('not.exist');

        cy.matchImageSnapshot();
      });

      it('port field is required', () => {
        initializeModal({ variant: 'create' });

        cy.get(`input[data-testid=${labelPort}_value`).clear();

        cy.get('body').click(0, 0);

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();
      });

      it('name length must be between 3 and 50 characters', () => {
        initializeModal({ variant: 'create' });

        cy.findAllByTestId(labelName).eq(1).clear().type('ab');

        cy.get('body').click(0, 0);

        cy.contains(labelNameMustBeAtLeast).should('be.visible');

        cy.matchImageSnapshot();
      });
      it('description field is not required', () => {
        initializeModal({ variant: 'create' });

        cy.findByLabelText(labelDescription).clear();

        cy.get('body').click(0, 0);

        cy.contains(labelRequired).should('not.exist');

        cy.matchImageSnapshot();
      });
      it('port should be a valid integer', () => {
        initializeModal({ variant: 'create' });

        cy.get(`input[data-testid=${labelPort}_value`).clear().type('0.1');

        cy.get('body').click(0, 0);

        cy.contains(labelInvalidPortNumber).should('be.visible');

        cy.matchImageSnapshot();
      });
      it('port should be between 0 and 65535', () => {
        initializeModal({ variant: 'create' });

        cy.get(`input[data-testid=${labelPort}_value`).clear().type('70000');

        cy.get('body').click(0, 0);

        cy.contains(labelInvalidPortNumber).should('be.visible');

        cy.matchImageSnapshot();
      });
      it('vcenter url must be a valid URL or an IP address', () => {
        initializeModal({ variant: 'create' });

        ['abc', '170.600.12', 'http://exa_mple.com'].forEach((url) => {
          cy.get('input[data-testid="URL_value"').clear().type(url);

          cy.get('body').click(0, 0);

          cy.contains(labelMustBeAvalidURL).should('be.visible');

          cy.matchImageSnapshot();
        });

        ['192.110.0.1/sdk', '170.12.12.1', 'http://example.com'].forEach(
          (url) => {
            cy.get('input[data-testid="URL_value"').clear().type(url);

            cy.get('body').click(0, 0);

            cy.contains(labelMustBeAvalidURL).should('not.exist');

            cy.matchImageSnapshot();
          }
        );
      });
    });

    describe('API requests', () => {
      it.only('sends a Post request when the the Modal is in "Creation Mode" and the Create Button is clicked ', () => {
        initializeModal({ variant: 'create' });

        cy.findByText(labelCreateConnectorConfiguration).should('be.visible');

        cy.get(`button[data-testid="submit"`)
          .should('have.text', labelCreate)
          .should('be.disabled');

        cy.findAllByTestId(labelName).eq(1).type('New name');

        cy.findByTestId(labelSelectPollers).click();

        cy.contains('poller1').click();

        cy.findByTestId(labelSelectPollers).click();

        cy.get(`input[data-testid="URL_value"`)
          .clear()
          .type('http://10.10.10.10/sdk');

        cy.get(`input[data-testid="Username_value"`).type('username');
        cy.get(`input[data-testid="Password_value"`).type('password');

        cy.get(`button[data-testid="submit"`).click();

        cy.contains(labelAdditionalConnectorCreated);

        cy.matchImageSnapshot();
      });
      it.only('sends an Update request when the Modal is in "Edition Mode" and the Update Button is clicked ', () => {
        initializeModal({ variant: 'update' });

        cy.findByText(labelCreateConnectorConfiguration).should('be.visible');

        cy.findAllByTestId(labelName).eq(1).clear().type('Updated name');

        cy.get(`button[data-testid="submit"`).click();

        cy.contains(labelAdditionalConnectorUpdated);

        cy.matchImageSnapshot();
      });
    });
  });
});
