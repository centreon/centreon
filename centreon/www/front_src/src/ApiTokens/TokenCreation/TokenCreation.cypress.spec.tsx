// button creation should exist
// clicking on it should display modal
// if the 3 inputs are empty the generate button should be disabled
// fill all inputs , button generate should be enabled , click , the token input should be displayed with a token on it
// input token should have a copy icon , when clicking the token must be copied

import { Method } from '@centreon/ui';

import { labelDuration, labelName } from '../../Resources/translatedLabels';
import { buildListEndpoint, listConfiguredUser } from '../api/endpoints';
import {
  labelCancel,
  labelCreateNewToken,
  labelGenerateNewToken,
  labelSecurityToken,
  labelTokenCreated,
  labelUser
} from '../translatedLabels';

import TokenCreationButton from './TokenCreationButton';

const interceptListConfiguredUsers = ({
  dataPath = 'apiTokens/creation/configuredUsers.json',
  parameters = { limit: 1, page: 1 },
  alias = 'getListConfiguredUsers'
}): void => {
  cy.fixture(dataPath).then((data) => {
    const endpoint = buildListEndpoint({
      endpoint: listConfiguredUser,
      parameters: { ...parameters }
    });
    cy.interceptAPIRequest({
      alias,
      method: Method.GET,
      path: `./api/latest${endpoint}`,
      response: data
    });
  });
};

const tokenName = 'slack';
const duration = { id: '7days', name: '7 days' };

describe('Api-token creation', () => {
  beforeEach(() => {
    cy.mount({
      Component: <TokenCreationButton />
    });
  });

  it('displays the token creation button', () => {
    cy.findByTestId(labelCreateNewToken).contains(labelCreateNewToken);

    cy.makeSnapshot();
  });

  it('displays the modal when clicking on token creation button', () => {
    cy.findByTestId(labelCreateNewToken).click();
    cy.findByTestId('modalTokenCreation').contains(labelCreateNewToken);

    cy.findByTestId('tokenName')
      .findByLabelText(labelName)
      .should('be.visible');

    cy.findByTestId('tokenNameInput').should('have.attr', 'required');

    cy.findByLabelText(labelDuration)
      .findByTestId(labelDuration)
      .should('be.visible')
      .should('have.attr', 'required');

    cy.findByLabelText(labelDuration)
      .findByTestId(labelDuration)
      .should('be.visible')
      .should('have.attr', 'required');

    cy.findByLabelText(labelUser)
      .findByTestId(labelUser)
      .should('be.visible')
      .should('have.attr', 'required');

    cy.findByLabelText(labelUser)
      .findByTestId(labelUser)
      .should('be.visible')
      .should('have.attr', 'required');

    cy.findByTestId(labelCancel).should('be.visible');
    cy.findByTestId('Confirm')
      .contains(labelGenerateNewToken)
      .should('be.visible')
      .should('be.disabled');

    cy.makeSnapshot();
  });

  it('displays an updated modal when clicking on generate token button   ', () => {
    interceptListConfiguredUsers({});
    cy.findByTestId('tokenNameInput').type(tokenName);
    cy.findByTestId('tokenNameInput').should('have.value', tokenName);

    cy.findByTestId(labelDuration).click();
    cy.findByRole('presentation', { name: duration.name })
      .should('be.visible')
      .click();
    cy.findByRole(labelDuration).should('have.value', duration.name);

    cy.findByTestId(labelUser).click();
    cy.waitForRequest('@getListConfiguredUsers');

    cy.fixture('apiTokens/creation/configuredUsers.json').then(({ result }) => {
      cy.findByRole('presentation', { name: result[0].name })
        .should('be.visible')
        .click();
      cy.findByRole(labelUser).should('have.value', result[0].name);
    });

    cy.findByTestId(labelCancel).should('be.visible');

    cy.findByTestId('Confirm')
      .contains(labelGenerateNewToken)
      .should('be.enabled');

    cy.makeSnapshot(
      'displays an updated create token button that becomes enabled when the required inputs are filled'
    );

    cy.findByTestId('Confirm').contains(labelGenerateNewToken).click();

    cy.contains(labelTokenCreated);
    cy.contains(labelSecurityToken);
  });
});
