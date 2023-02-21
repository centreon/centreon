import {
  applyConfigurationViaClapi,
  executeActionViaClapi
} from '../../commons';

interface DataToUseForCheckForm {
  custom?: () => void;
  selector: string;
  value?: string;
}

const millisecondsValueForSixMonth = 15901200;
const millisecondsValueForFourHour = 14400;

const initializeConfigACLAndGetLoginPage = (): Cypress.Chainable => {
  return cy
    .executeCommandsViaClapi(
      'resources/clapi/config-ACL/local-authentication-acl-user.json'
    )
    .executeCommandsViaClapi(
      'resources/clapi/config-ACL/local-authentication-acl-user-non-admin.json'
    )
    .then(applyConfigurationViaClapi)
    .then(() => cy.fixture('users/admin.json'));
};

const removeContact = (): Cypress.Chainable => {
  return cy.setUserTokenApiV1().then(() => {
    executeActionViaClapi({
      action: 'DEL',
      object: 'CONTACT',
      values: 'user1'
    });
    executeActionViaClapi({
      action: 'DEL',
      object: 'CONTACT',
      values: 'user2'
    });
  });
};

const getUserContactId = (userName: string): Cypress.Chainable => {
  const query = `SELECT contact_id FROM contact WHERE contact_name = '${userName}';`;
  const command = `docker exec -i ${Cypress.env(
    'dockerName'
  )} mysql -ucentreon -pcentreon centreon <<< "${query}"`;

  return cy
    .exec(command, { failOnNonZeroExit: true, log: true })
    .then(({ code, stdout, stderr }) => {
      if (!stderr && code === 0) {
        const idUser = parseInt(stdout.split('\n')[1], 10);

        return cy.wrap(idUser || '0');
      }

      return cy.log(`Can't execute command on database.`);
    });
};

const checkDefaultsValueForm: Array<DataToUseForCheckForm> = [
  {
    selector: '#Minimumpasswordlength',
    value: '12'
  },
  {
    custom: (): void => {
      cy.get('#Passwordmustcontainlowercase').should(
        'have.class',
        'MuiButton-containedPrimary'
      );
    },
    selector: '#Passwordmustcontainlowercase',
    value: ''
  },
  {
    custom: (): void => {
      cy.get('#Passwordmustcontainuppercase').should(
        'have.class',
        'MuiButton-containedPrimary'
      );
    },

    selector: '#Passwordmustcontainuppercase',
    value: ''
  },
  {
    custom: (): void => {
      cy.get('#Passwordmustcontainnumbers').should(
        'have.class',
        'MuiButton-containedPrimary'
      );
    },

    selector: '#Passwordmustcontainnumbers',
    value: ''
  },
  {
    custom: (): void => {
      cy.get('#Passwordmustcontainspecialcharacters').should(
        'have.class',
        'MuiButton-containedPrimary'
      );
    },

    selector: '#Passwordmustcontainspecialcharacters',
    value: ''
  },
  {
    selector: '#PasswordexpiresafterpasswordExpirationexpirationDelayMonth',
    value: '6'
  },
  {
    selector: '#PasswordexpiresafterpasswordExpirationexpirationDelayDay',
    value: '0'
  },
  {
    custom: (): void => {
      cy.get('div[name="excludedUsers"]')
        .find('span')
        .contains('centreon-gorgone');
    },
    selector: '#Excludedusers',
    value: ''
  },
  {
    selector: '#MinimumtimebetweenpasswordchangesdelayBeforeNewPasswordDay',
    value: '0'
  },
  {
    selector: '#MinimumtimebetweenpasswordchangesdelayBeforeNewPasswordHour',
    value: '1'
  },
  {
    selector: '#Last3passwordscanbereused',
    value: 'on'
  },
  {
    selector: '#Numberofattemptsbeforeuserisblocked',
    value: '5'
  },
  {
    selector:
      '#TimethatmustpassbeforenewconnectionisallowedblockingDurationDay',
    value: '0'
  },
  {
    selector:
      '#TimethatmustpassbeforenewconnectionisallowedblockingDurationHour',
    value: '0'
  },
  {
    selector:
      '#TimethatmustpassbeforenewconnectionisallowedblockingDurationMinute',
    value: '15'
  }
];

export {
  millisecondsValueForSixMonth,
  millisecondsValueForFourHour,
  getUserContactId,
  removeContact,
  initializeConfigACLAndGetLoginPage,
  checkDefaultsValueForm,
  DataToUseForCheckForm
};
