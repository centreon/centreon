import {
  labelAddCommand,
  labelCommandLine,
  labelComments,
  labelModifyCommand,
  labelName,
  labelRequired,
  labelSave,
  labelSelectOptimizationConnector
} from '../translatedLabels';
import initialize from './initialize';

const clickOutideTheField = () => cy.findByTestId('Modal-header').click();

export default (): void => {
  describe('Modal', () => {
    beforeEach(() => {
      cy.viewport('macbook-15');

      initialize();
    });

    it('displays form fields with default values when the Modal is opened in Creation Mode', () => {
      cy.waitForRequest('@getCommands');

      cy.get(`[data-testid="add-resource"]`).click();

      cy.findByText(labelAddCommand).should('be.visible');

      cy.findAllByTestId(labelName)
        .eq(1)
        .should('be.visible')
        .should('have.value', '');

      cy.get('input[name="Notification"]').should('not.be.checked');
      cy.get('input[name="Check "]').should('not.be.checked');
      cy.get('input[name="Discovery"]').should('not.be.checked');
      cy.get('input[name="Miscellaneous"]').should('not.be.checked');

      cy.findAllByTestId(labelCommandLine).eq(1).should('be.empty');
      cy.findByTestId('enable-shell-syntax')
        .find('input')
        .should('not.be.checked');

      cy.findByTestId(labelSelectOptimizationConnector).should(
        'have.value',
        ''
      );
      cy.findAllByTestId(labelComments).eq(1).should('be.empty');

      cy.matchImageSnapshot();

      cy.findByLabelText('close').click();
    });
    it('displays form fields with the selected command values when the Modal is opened in Edition Mode', () => {
      cy.waitForRequest('@getCommands');

      cy.contains('check_host_alive').click();

      cy.findByText(labelModifyCommand).should('be.visible');

      cy.findAllByTestId(labelName)
        .eq(1)
        .should('be.visible')
        .should('have.value', 'check_host_alive');

      cy.get('input[name="Notification"]').should('be.checked');
      cy.get('input[name="Check "]').should('not.be.checked');
      cy.get('input[name="Discovery"]').should('not.be.checked');
      cy.get('input[name="Miscellaneous"]').should('not.be.checked');

      cy.findAllByTestId(labelCommandLine)
        .eq(1)
        .should(
          'have.value',
          '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$ -v $_HOSTSNMPVERSION$ -C $_HOSTSNMPCOMMUNITY$ -n -p $ARG1$ -w $ARG2$ -c $ARG3$'
        );

      cy.findByTestId('enable-shell-syntax').find('input').should('be.checked');
      cy.findByTestId(labelSelectOptimizationConnector).should(
        'have.value',
        'Telegraf'
      );

      cy.findAllByTestId(labelComments)
        .eq(1)
        .should('have.value', 'some comment');

      cy.matchImageSnapshot();

      cy.findByLabelText('close').click();
    });

    it('disables the save button when no change has been made to the modal form', () => {
      cy.waitForRequest('@getCommands');

      cy.contains('check_host_alive').click();

      cy.findByText(labelModifyCommand).should('be.visible');

      cy.get(`button[data-testid="submit"`)
        .should('have.text', labelSave)
        .should('be.disabled');

      cy.matchImageSnapshot();

      cy.findByLabelText('close').click();
    });
    it('disables the save button when there is error(s) in the form', () => {
      cy.waitForRequest('@getCommands');

      cy.contains('check_host_alive').click();

      cy.findByText(labelModifyCommand).should('be.visible');

      cy.findAllByTestId(labelName).eq(1).clear();

      cy.get(`button[data-testid="submit"`)
        .should('have.text', labelSave)
        .should('be.disabled');

      cy.matchImageSnapshot();

      cy.findByLabelText('close').click();

      cy.contains('Leave').click();
    });
    it('enables the create button when all mandatory fields are filled', () => {
      cy.waitForRequest('@getCommands');

      cy.get(`[data-testid="add-resource"]`).click();

      cy.findByText(labelAddCommand).should('be.visible');

      cy.get(`button[data-testid="submit"`)
        .should('have.text', labelSave)
        .should('be.disabled');

      cy.findAllByTestId(labelName).eq(1).type('New name');

      cy.get('input[name="Notification"]').click();

      cy.findAllByTestId(labelCommandLine)
        .eq(1)
        .type('$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$');

      cy.get(`button[data-testid="submit"`)
        .should('have.text', labelSave)
        .should('not.be.disabled');

      cy.matchImageSnapshot();

      cy.findByLabelText('close').click();
      cy.findByLabelText('Discard').click();
    });

    describe('Form validation', () => {
      it('validates that the name field is required', () => {
        cy.waitForRequest('@getCommands');

        cy.contains('check_host_alive').click();

        cy.findAllByTestId(labelName).eq(1).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');

        cy.findByLabelText('close').click();
        cy.findByLabelText('Leave').click();
      });

      it('validates that the command line field is required', () => {
        cy.waitForRequest('@getCommands');
        cy.contains('check_host_alive').click();

        cy.findAllByTestId(labelCommandLine).eq(1).clear();
        clickOutideTheField();

        cy.contains(labelRequired).should('be.visible');

        cy.findByLabelText('close').click();
        cy.findByLabelText('Leave').click();
      });

      it('validates that the connector field is not required', () => {
        cy.waitForRequest('@getCommands');
        cy.contains('check_host_alive').click();

        cy.findByTestId(labelSelectOptimizationConnector).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('not.exist');

        cy.get(`button[data-testid="submit"`)
          .should('have.text', labelSave)
          .should('not.be.disabled');

        cy.findByLabelText('close').click();
        cy.findByLabelText('Discard').click();
      });

      it('validates that comment field is not required', () => {
        cy.waitForRequest('@getCommands');
        cy.contains('check_host_alive').click();

        cy.findAllByTestId(labelComments).eq(1).clear();

        clickOutideTheField();

        cy.contains(labelRequired).should('not.exist');

        cy.get(`button[data-testid="submit"`)
          .should('have.text', labelSave)
          .should('not.be.disabled');

        cy.findByLabelText('close').click();
        cy.findByLabelText('Discard').click();
      });
    });

    describe('API requests', () => {
      it('sends a Post request when the Modal is in "Creation Mode" and the Create Button is clicked', () => {
        cy.waitForRequest('@getCommands');
        cy.get(`[data-testid="add-resource"]`).click();

        cy.findByText(labelAddCommand).should('be.visible');

        cy.get(`button[data-testid="submit"`)
          .should('have.text', labelSave)
          .should('be.disabled');

        cy.findAllByTestId(labelName).eq(1).type('New name');

        cy.get('input[name="Discovery"]').click();

        cy.findAllByTestId(labelCommandLine)
          .eq(1)
          .type('$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$');

        cy.findByTestId('enable-shell-syntax').find('input').click();

        cy.findByTestId(labelSelectOptimizationConnector).click({
          force: true
        });

        cy.contains('Telegraf').click();

        cy.findAllByTestId(labelComments).eq(1).type('some comment');

        cy.get(`button[data-testid="submit"`).click();

        cy.contains('Command created');

        cy.waitForRequest('@createCommand').then(({ request }) => {
          expect(request.body).to.deep.equals({
            command_line:
              '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$',
            comment: 'some comment',
            connector: '/centreon/api/latest/configuration/connectors/4',
            is_shell_enabled: true,
            name: 'New name',
            type: 'Discovery'
          });
        });

        cy.matchImageSnapshot();
      });
      it('sends a PATCH request when the Modal is in "Edition Mode" and the Update Button is clicked.', () => {
        cy.waitForRequest('@getCommands');
        cy.contains('check_host_alive').click();

        cy.findByText(labelModifyCommand).should('be.visible');

        cy.findAllByTestId(labelName).eq(1).clear().type('Updated name');

        cy.get('input[name="Discovery"]').click();

        cy.findByTestId('enable-shell-syntax').find('input').click();

        cy.get(`button[data-testid="submit"`).click();

        cy.waitForRequest('@updateCommand').then(({ request }) => {
          expect(request.body).to.deep.equals({
            command_line:
              '$USER1$/check_centreon_snmp_process -H $HOSTADDRESS$ -v $_HOSTSNMPVERSION$ -C $_HOSTSNMPCOMMUNITY$ -n -p $ARG1$ -w $ARG2$ -c $ARG3$',
            comment: 'some comment',
            connector:
              '/centreon/api/latest/.well-known/genid/7b9eb72f31977bee3cf4',
            is_shell_enabled: false,
            name: 'Updated name',
            type: 'Discovery'
          });
        });

        cy.contains('Command updated');

        cy.matchImageSnapshot();
      });
    });

    describe('Ask Before quit popup', () => {
      it('displays a modal when the form is updated with errors and the cancel button is clicked', () => {
        cy.waitForRequest('@getCommands');
        cy.contains('check_host_alive').click();

        cy.findAllByTestId(labelName).eq(1).clear();

        cy.contains('Cancel').click();

        cy.contains('Do you want to leave this page?').should('be.visible');

        cy.makeSnapshot();

        cy.findByLabelText('Leave').click();
      });

      it('displays a modal when the form is updated and the cancel button is clicked', () => {
        cy.waitForRequest('@getCommands');
        cy.contains('check_host_alive').click();

        cy.findAllByTestId(labelName).eq(1).type('New name');
        cy.contains('Cancel').click();

        cy.contains('Do you want to save the changes?').should('be.visible');

        cy.makeSnapshot();

        cy.findByLabelText('Discard').click();
      });
    });
  });
};
