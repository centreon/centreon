import { Provider, createStore } from 'jotai';
import { keys } from 'ramda';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { DialogState } from '../Listing/models';
import AdditionalConnectorModal from '../Modal/Modal';
import { ParameterKeys } from '../Modal/models';
import {
  additionalConnectorsEndpoint,
  getAdditionalConnectorEndpoint,
  getPollersForConnectorTypeEndpoint
} from '../api/endpoints';
import { dialogStateAtom } from '../atoms';
import {
  labelAdditionalConnectorCreated,
  labelAdditionalConnectorUpdated,
  labelAddvCenterESX,
  labelAteastOnePollerIsRequired,
  labelCancel,
  labelCreate,
  labelCreateConnectorConfiguration,
  labelDescription,
  labelInvalidPortNumber,
  labelMustBeAvalidURL,
  labelName,
  labelNameMustBeAtLeast,
  labelParameters,
  labelRemoveVCenterESX,
  labelRequired,
  labelSelectPollers,
  labelType,
  labelUpdate,
  labelUpdateConnectorConfiguration,
  labelVcenterNameMustBeUnique
} from '../translatedLabels';
import { defaultParameters } from '../utils';

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

    cy.interceptAPIRequest({
      alias: 'createConnector',
      method: Method.POST,
      path: `**${additionalConnectorsEndpoint}**`,
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
  });

  cy.interceptAPIRequest({
    alias: 'updateConnector',
    method: Method.PUT,
    path: `**${getAdditionalConnectorEndpoint(1)}**`,
    response: {}
  });
};

interface InitializeModal {
  variant?: 'create' | 'update';
}

const store = createStore();

const initializeModal = ({ variant = 'create' }: InitializeModal): void => {
  const dialogState: DialogState = {
    connector: { id: 1 },
    isOpen: true,
    variant
  };

  store.set(dialogStateAtom, dialogState);
  mockPageRequests();

  cy.viewport(1200, 1000);

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={store}>
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

const clickOutideTheField = () => cy.findByTestId('Modal-header').click();

export default (): void => {
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
        .should('have.text', 'VMWare 6/7');

      cy.findByTestId(labelSelectPollers).should('be.visible');

      cy.findByText(labelParameters).should('be.visible');
      cy.findAllByTestId('parameterGroup').should('have.length', 1);

      keys(defaultParameters).forEach((parameter) => {
        cy.get(`input[data-testid="${parameter}_value"`)
          .should('be.visible')
          .should('have.value', defaultParameters[parameter])
          .should('not.be.disabled');
      });

      cy.findByText(labelAddvCenterESX)
        .should('be.visible')
        .should('not.be.disabled');

      cy.get('input[name="port"]')
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
    it('displays form fields with the selected ACC values when the Modal is opened in Edition Mode', () => {
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
        .should('have.text', 'VMWare 6/7');

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

      cy.get('input[name="port"]')
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
    it('disables the create/update button when there is error(s) in the form', () => {
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
    it('enables the create button when all mandatory fields are filled', () => {
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

    it('hides Delete Parameter Group buttons when there is only one parameter group', () => {
      initializeModal({ variant: 'create' });

      cy.findByTestId(labelRemoveVCenterESX).should('not.exist');
    });

    it(`adds a new parameter group when "Add vCenter ESX" button is clicked`, () => {
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
      it('validates that the name field is required', () => {
        initializeModal({ variant: 'create' });

        cy.findAllByTestId(labelName).eq(1).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');
      });

      it('validates that at least one poller is required', () => {
        initializeModal({ variant: 'create' });

        cy.findByTestId(labelSelectPollers).click();

        cy.contains('poller1').click();
        clickOutideTheField();

        cy.findByTestId('CancelIcon').click();

        cy.matchImageSnapshot();

        cy.contains(labelAteastOnePollerIsRequired).should('be.visible');
      });

      it('validates that vCenter name field is required', () => {
        initializeModal({ variant: 'create' });

        cy.get(`input[data-testid="vCenter name_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();
      });

      it('validates that vCenter name must be unique for its own ACC', () => {
        initializeModal({ variant: 'create' });

        cy.findByText(labelAddvCenterESX).click();

        cy.get(`input[data-testid="vCenter name_value"`)
          .eq(0)
          .clear()
          .type('vcenter1');

        cy.get(`input[data-testid="vCenter name_value"`)
          .eq(1)
          .clear()
          .type('vcenter1');

        clickOutideTheField();

        cy.contains(labelVcenterNameMustBeUnique).should('be.visible');

        cy.matchImageSnapshot();
      });

      it('validates that vCenter URL field is required', () => {
        initializeModal({ variant: 'create' });

        cy.get(`input[data-testid="URL_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();
      });

      it('validates that vCenter username is required in Creation Mode', () => {
        initializeModal({ variant: 'create' });

        cy.get(`input[data-testid="Username_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();
      });

      it('validates that vCenter username is not required Edition Mode', () => {
        initializeModal({ variant: 'update' });

        cy.get(`input[data-testid="Username_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('not.exist');

        cy.matchImageSnapshot();
      });

      it('validates that vCenter password field is required in Creation Mode', () => {
        initializeModal({ variant: 'create' });

        cy.get(`input[data-testid="Password_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();
      });

      it('validates that vCenter password field is not required in Edition Mode', () => {
        initializeModal({ variant: 'update' });

        cy.get(`input[data-testid="Password_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('not.exist');

        cy.matchImageSnapshot();
      });

      it('validates that port field is required', () => {
        initializeModal({ variant: 'create' });

        cy.get('input[name="port"]').clear().blur();

        cy.contains(labelInvalidPortNumber).should('be.visible');

        cy.matchImageSnapshot();
      });

      it('validates that name length must be between 3 and 50 characters', () => {
        initializeModal({ variant: 'create' });

        cy.findAllByTestId(labelName).eq(1).clear().type('ab');

        clickOutideTheField();

        cy.contains(labelNameMustBeAtLeast).should('be.visible');

        cy.matchImageSnapshot();
      });
      it('validates that the description field is not required', () => {
        initializeModal({ variant: 'create' });

        cy.findByLabelText(labelDescription).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('not.exist');
      });
      it('validates that the port should be a valid integer', () => {
        initializeModal({ variant: 'create' });

        cy.get('input[name="port"]').clear().type('0.1');

        clickOutideTheField();

        cy.contains(labelInvalidPortNumber).should('be.visible');

        cy.matchImageSnapshot();
      });
      it('validates that the port should be between 0 and 65535', () => {
        initializeModal({ variant: 'create' });

        cy.get('input[name="port"]').clear().type('70000');

        clickOutideTheField();

        cy.contains(labelInvalidPortNumber).should('be.visible');

        cy.matchImageSnapshot();
      });
      it('validates that vcenter url must be a valid URL or an IP address', () => {
        initializeModal({ variant: 'create' });

        ['abc', '170.600.12', 'http://exa_mple.com'].forEach((url, index) => {
          cy.get('input[data-testid="URL_value"').clear().type(url);

          clickOutideTheField();

          cy.contains(labelMustBeAvalidURL).should('be.visible');

          cy.matchImageSnapshot(`invalide_url_${index}`);
        });

        ['192.110.0.1/sdk', '170.12.12.1', 'http://example.com'].forEach(
          (url, index) => {
            cy.get('input[data-testid="URL_value"').clear().type(url);

            clickOutideTheField();

            cy.contains(labelMustBeAvalidURL).should('not.exist');

            cy.matchImageSnapshot(`valide_url_${index}`);
          }
        );
      });
    });

    describe('API requests', () => {
      it('sends a Post request when the Modal is in "Creation Mode" and the Create Button is clicked', () => {
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

        cy.waitForRequest('@createConnector').then(({ request }) => {
          expect(request.body).equals(
            '{"description":null,"name":"New name","parameters":{"port":5700,"vcenters":[{"name":"my_vcenter","password":"password","url":"http://10.10.10.10/sdk","username":"username"}]},"pollers":[1],"type":"vmware_v6"}'
          );
        });

        cy.matchImageSnapshot();
      });
      it('sends an Update request when the Modal is in "Edition Mode" and the Update Button is clicked.', () => {
        initializeModal({ variant: 'update' });

        cy.findByText(labelUpdateConnectorConfiguration).should('be.visible');

        cy.findAllByTestId(labelName).eq(1).clear().type('Updated name');
        cy.get('input[name=port]').clear().type('100');

        cy.get(`button[data-testid="submit"`).click();

        cy.waitForRequest('@updateConnector').then(({ request }) => {
          expect(request.body).equals(
            '{"name":"Updated name","description":"Description for VMWare1","parameters":{"port":1000,"vcenters":[{"name":"vCenter1","password":"password1","url":"https://vcenter1.example.com/sdk","username":"user1"},{"name":"vCenter2","password":"password2","url":"192.0.0.1","username":"user2"}]},"pollers":[101,102],"type":"vmware_v6"}'
          );
        });

        cy.contains(labelAdditionalConnectorUpdated);

        cy.matchImageSnapshot();
      });
    });
  });
};
