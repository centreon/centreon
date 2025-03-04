import { applyConfigurationViaClapi } from '../../../commons';

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
    selector: '[data-testid="local_passwordExpirationMonths"]',
    value: '5'
  },
  {
    selector: '#PasswordexpiresafterpasswordExpirationexpirationDelayDay',
    value: '27'
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
    selector: '[data-testid="local_timeBetweenPasswordChangesDays"]',
    value: '0'
  },
  {
    selector: '[data-testid="local_timeBetweenPasswordChangesHours"]',
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
      '[aria-label="Time that must pass before new connection is allowed Day"]',
    value: '0'
  },
  {
    selector:
      '[aria-label="Time that must pass before new connection is allowed Hour"]',
    value: '0'
  },
  {
    selector:
      '[aria-label="Time that must pass before new connection is allowed Minutes"]',
    value: '15'
  }
];

export {
  millisecondsValueForSixMonth,
  millisecondsValueForFourHour,
  initializeConfigACLAndGetLoginPage,
  checkDefaultsValueForm,
  DataToUseForCheckForm
};
