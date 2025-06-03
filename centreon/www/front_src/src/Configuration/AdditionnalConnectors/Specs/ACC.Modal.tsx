import { keys } from 'ramda';

import { defaultParameters } from '../utils';

import { ParameterKeys } from '../models';
import initialize from './initialize';

import {
  labelAddAnAdditionalConfigurations,
  labelAdditionalConnectorCreated,
  labelAdditionalConnectorUpdated,
  labelAddvCenterESX,
  labelAteastOnePollerIsRequired,
  labelCancel,
  labelDescription,
  labelInvalidPortNumber,
  labelModifyConnectorConfiguration,
  labelMustBeAvalidURL,
  labelName,
  labelNameMustBeAtLeast,
  labelRemoveVCenterESX,
  labelRequired,
  labelSave,
  labelSelectPollers,
  labelType,
  labelVcenterNameMustBeUnique,
  labelvCenterESX
} from '../translatedLabels';

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
    beforeEach(initialize);

    it('displays form fields with default values when the Modal is opened in Creation Mode', () => {
      cy.waitForRequest('@getConnectors');

      cy.get(`[data-testid="add-resource"]`).click();

      cy.findByText(labelAddAnAdditionalConfigurations).should('be.visible');

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

      cy.findByText(labelvCenterESX).should('be.visible');
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
        .should('have.text', labelCancel)
        .should('not.be.disabled');

      cy.get(`button[data-testid="submit"`)
        .should('have.text', labelSave)
        .should('be.disabled');

      cy.findByLabelText('close').click();
    });
    it('displays form fields with the selected ACC values when the Modal is opened in Edition Mode', () => {
      cy.waitForRequest('@getConnectors');

      cy.contains('VMWare1').click();

      cy.findByText(labelModifyConnectorConfiguration).should('be.visible');

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

      cy.findByText(labelvCenterESX).should('be.visible');
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
        .should('have.text', labelCancel)
        .should('not.be.disabled');

      cy.get(`button[data-testid="submit"`)
        .should('have.text', labelSave)
        .should('be.disabled');

      cy.matchImageSnapshot();

      cy.findByLabelText('close').click();
    });

    it('disables the save button when no change has been made to the modal form', () => {
      cy.waitForRequest('@getConnectors');

      cy.contains('VMWare1').click();

      cy.findByText(labelModifyConnectorConfiguration).should('be.visible');

      cy.findByTestId('Modal')
        .children()
        .eq(2)
        .children()
        .first()
        .scrollTo('bottom');

      cy.get(`button[data-testid="submit"`)
        .should('have.text', labelSave)
        .should('be.disabled');

      cy.matchImageSnapshot();

      cy.findByLabelText('close').click();
    });
    it('disables the save button when there is error(s) in the form', () => {
      cy.waitForRequest('@getConnectors');

      cy.contains('VMWare1').click();

      cy.findByText(labelModifyConnectorConfiguration).should('be.visible');

      cy.findAllByTestId(labelName).eq(1).clear();

      cy.findByTestId('Modal')
        .children()
        .eq(2)
        .children()
        .first()
        .scrollTo('bottom');

      cy.get(`button[data-testid="submit"`)
        .should('have.text', labelSave)
        .should('be.disabled');

      cy.findByLabelText('close').click();
      cy.contains('Leave').click();
    });
    it('enables the create button when all mandatory fields are filled', () => {
      cy.waitForRequest('@getConnectors');

      cy.get(`[data-testid="add-resource"]`).click();

      cy.findByText(labelAddAnAdditionalConfigurations).should('be.visible');

      cy.get(`button[data-testid="submit"`)
        .should('have.text', labelSave)
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
        .should('have.text', labelSave)
        .should('not.be.disabled');

      cy.matchImageSnapshot();

      cy.findByLabelText('close').click();
      cy.findByLabelText('Discard').click();
    });

    it('hides Delete Parameter Group buttons when there is only one parameter group', () => {
      cy.waitForRequest('@getConnectors');

      cy.get(`[data-testid="add-resource"]`).click();
      cy.findByTestId(labelRemoveVCenterESX).should('not.exist');

      cy.findByLabelText('close').click();
    });

    it(`adds a new parameter group when "Add vCenter ESX" button is clicked`, () => {
      cy.waitForRequest('@getConnectors');

      cy.get(`[data-testid="add-resource"]`).click();

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

      cy.findByLabelText('close').click();
      cy.findByLabelText('Leave').click();
    });
    it(`removes a parameter group when the ${labelRemoveVCenterESX} button is clicked`, () => {
      cy.waitForRequest('@getConnectors');

      cy.get(`[data-testid="add-resource"]`).click();

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

      cy.findByLabelText('close').click();
    });

    describe('Form validation', () => {
      it('validates that the name field is required', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.findAllByTestId(labelName).eq(1).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');

        cy.findByLabelText('close').click();
      });

      it('validates that at least one poller is required', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.findByTestId(labelSelectPollers).click();

        cy.contains('poller1').click();
        clickOutideTheField();

        cy.findByTestId('CancelIcon').click();

        cy.matchImageSnapshot();

        cy.contains(labelAteastOnePollerIsRequired).should('be.visible');

        cy.findByLabelText('close').click();
      });

      it('validates that vCenter name field is required', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.get(`input[data-testid="vCenter name_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();

        cy.findByLabelText('close').click();
        cy.contains('Leave').click();
      });

      it('validates that vCenter name must be unique for its own ACC', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

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

        cy.findByLabelText('close').click();
        cy.contains('Leave').click();
      });

      it('validates that vCenter URL field is required', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.get(`input[data-testid="URL_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();

        cy.findByLabelText('close').click();
        cy.contains('Leave').click();
      });

      it('validates that vCenter username is required in Creation Mode', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.get(`input[data-testid="Username_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();

        cy.findByLabelText('close').click();
      });

      it('validates that vCenter username is not required Edition Mode', () => {
        cy.waitForRequest('@getConnectors');

        cy.contains('VMWare1').click();

        cy.get(`input[data-testid="Username_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('not.exist');

        cy.matchImageSnapshot();

        cy.findByLabelText('close').click();
        cy.findByLabelText('Discard').click();
      });

      it('validates that vCenter password field is required in Creation Mode', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.get(`input[data-testid="Password_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');

        cy.matchImageSnapshot();

        cy.findByLabelText('close').click();
      });

      it('validates that vCenter password field is not required in Edition Mode', () => {
        cy.waitForRequest('@getConnectors');

        cy.contains('VMWare1').click();

        cy.get(`input[data-testid="Password_value"`).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('not.exist');

        cy.matchImageSnapshot();

        cy.findByLabelText('close').click();
        cy.findByLabelText('Discard').click();
      });

      it('validates that port field is required', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.get('input[name="port"]').clear().blur();

        cy.contains(labelInvalidPortNumber).should('be.visible');

        cy.matchImageSnapshot();

        cy.findByLabelText('close').click();
        cy.findByLabelText('Leave').click();
      });

      it('validates that name length must be between 3 and 50 characters', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.findAllByTestId(labelName).eq(1).clear().type('ab');

        clickOutideTheField();

        cy.contains(labelNameMustBeAtLeast).should('be.visible');

        cy.matchImageSnapshot();

        cy.findByLabelText('close').click();
        cy.findByLabelText('Leave').click();
      });

      it('validates that the description field is not required', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.findByLabelText(labelDescription).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('not.exist');

        cy.findByLabelText('close').click();
      });

      it('validates that the port should be a valid integer', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.get('input[name="port"]').clear().type('0.1');

        clickOutideTheField();

        cy.contains(labelInvalidPortNumber).should('be.visible');

        cy.matchImageSnapshot();

        cy.findByLabelText('close').click();
        cy.findByLabelText('Leave').click();
      });

      it('validates that the port should be between 0 and 65535', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.get('input[name="port"]').clear().type('70000');

        clickOutideTheField();

        cy.contains(labelInvalidPortNumber).should('be.visible');

        cy.matchImageSnapshot();

        cy.findByLabelText('close').click();
        cy.findByLabelText('Leave').click();
      });

      it('validates that vcenter url must be a valid URL or an IP address', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

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

        cy.findByLabelText('close').click();
        cy.contains('Leave').click();
      });
    });

    describe('API requests', () => {
      it('sends a Post request when the Modal is in "Creation Mode" and the Create Button is clicked', () => {
        cy.waitForRequest('@getConnectors');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.findByText(labelAddAnAdditionalConfigurations).should('be.visible');

        cy.get(`button[data-testid="submit"`)
          .should('have.text', labelSave)
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
          expect(request.body).to.deep.equals({
            description: null,
            name: 'New name',
            parameters: {
              port: 5700,
              vcenters: [
                {
                  name: 'my_vcenter',
                  password: 'password',
                  url: '10.10.10.10/sdk',
                  scheme: 'http',
                  username: 'username'
                }
              ]
            },
            pollers: [1],
            type: 'vmware_v6'
          });
        });

        cy.matchImageSnapshot();
      });
      it('sends an Update request when the Modal is in "Edition Mode" and the Update Button is clicked.', () => {
        cy.waitForRequest('@getConnectors');

        cy.contains('VMWare1').click();

        cy.findByText(labelModifyConnectorConfiguration).should('be.visible');

        cy.findAllByTestId(labelName).eq(1).clear().type('Updated name');
        cy.get('input[name=port]').clear().type('100');

        cy.get(`button[data-testid="submit"`).click();

        cy.waitForRequest('@updateConnector').then(({ request }) => {
          expect(request.body).to.deep.equals({
            name: 'Updated name',
            description: 'Description for VMWare1',
            parameters: {
              port: 1000,
              vcenters: [
                {
                  name: 'vCenter1',
                  password: 'password1',
                  url: 'vcenter1.example.com/sdk',
                  username: 'user1',
                  scheme: 'https'
                },
                {
                  name: 'vCenter2',
                  password: 'password2',
                  url: '192.0.0.1',
                  username: 'user2',
                  scheme: null
                }
              ]
            },
            pollers: [101, 102],
            type: 'vmware_v6'
          });
        });

        cy.contains(labelAdditionalConnectorUpdated);

        cy.matchImageSnapshot();
      });
    });

    describe('Ask Before quit popup', () => {
      it('displays a modal when the form is updated with errors and the cancel button is clicked', () => {
        cy.waitForRequest('@getConnectors');

        cy.contains('VMWare1').click();

        cy.findAllByTestId('vCenter name_value').eq(1).clear();
        cy.findAllByTestId('vCenter name_value').eq(1).blur();
        cy.contains(labelCancel).click();

        cy.contains('Do you want to leave this page?').should('be.visible');

        cy.makeSnapshot();

        cy.findByLabelText('Leave').click();
      });

      it('displays a modal when the form is updated and the cancel button is clicked', () => {
        cy.waitForRequest('@getConnectors');
        cy.contains('VMWare1').click();

        cy.findAllByTestId(labelName).eq(1).type('New name');
        cy.contains(labelCancel).click({ force: true });

        cy.contains('Do you want to save the changes?').should('be.visible');

        cy.makeSnapshot();

        cy.findByLabelText('Discard').click();
      });
    });
  });
};
