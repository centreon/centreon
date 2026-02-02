import dayjs from 'dayjs';
import LocalizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router-dom';

import {
  Method,
  QueryParameter,
  SnackbarProvider,
  TestQueryProvider
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { labelAdd } from '../Resources/translatedLabels';

import TokenListing from './TokenListing/TokenListing';
import {
  createTokenEndpoint,
  deleteTokenEndpoint,
  listConfiguredUser,
  listTokensEndpoint
} from './api/endpoints';
import {
  labelCancel,
  labelCreateNewToken,
  labelDeleteToken,
  labelDuration,
  labelGenerateNewToken,
  labelName,
  labelSecurityToken,
  labelTokenCreated,
  labelTokenDeletedSuccessfully,
  labelUser
} from './translatedLabels';

dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(LocalizedFormat);

const fillInputs = (): void => {
  cy.fixture('apiTokens/creation/configuredUsers.json').then((data) => {
    cy.interceptAPIRequest({
      alias: 'getListConfiguredUsers',
      method: Method.GET,
      path: `./api/latest${listConfiguredUser}**`,
      response: data
    });
  });
  cy.findByTestId('tokenNameInput').type(tokenName);
  cy.findByTestId(labelUser).click();
  cy.waitForRequest('@getListConfiguredUsers');

  cy.fixture('apiTokens/creation/configuredUsers.json').then(({ result }) => {
    cy.findByRole('option', { name: result[1].name }).click();
  });
};
const openDialog = (): void => {
  cy.findByTestId(labelCreateNewToken).click();
  cy.waitForRequest('@getListTokens');
  cy.findByTestId('tokenCreationDialog').contains(labelCreateNewToken);
};

const checkDataInputs = ({ durationValue, userValue, token, name }): void => {
  cy.findByTestId('tokenNameInput').should('have.value', name);
  cy.findByTestId(labelDuration).should('have.value', durationValue);
  cy.findByTestId(labelUser).should('have.value', userValue);
  cy.findByTestId('tokenInput').should('have.value', token);
};
const checkModalInformationWithGeneratedToken = ({
  data,
  durationValue
}): void => {
  const { name, token } = data;

  checkDataInputs({
    durationValue,
    name,
    token,
    userValue: data.user.name
  });
};

const checkTokenInput = (token: string): void => {
  cy.findByTestId('tokenInput').should('have.value', token);
  cy.findByTestId('token')
    .findByTestId('VisibilityOffIcon')
    .should('be.visible');
  cy.makeSnapshot('token input with encrypted password');

  cy.findByTestId('VisibilityOffIcon').parent().click();
  cy.findByTestId('token').findByTestId('VisibilityIcon').should('be.visible');
  cy.makeSnapshot('token input with displayed password');
};

const tokenName = 'slack';
const duration = { id: '1year', name: '1 year' };

interface Query {
  name: string;
  value: string;
}
interface InterceptListTokens {
  alias: string;
  customQueryParameters?: Array<QueryParameter> | null;
  dataPath: string;
  method?: Method;
  query?: Query;
}

const interceptListTokens = ({
  dataPath = 'apiTokens/listing/list.json',
  alias = 'getListTokens',
  method = Method.GET,
  query
}: InterceptListTokens): void => {
  cy.fixture(dataPath).then((data) => {
    cy.interceptAPIRequest({
      alias,
      method,
      path: `**${listTokensEndpoint}**`,
      query,
      response: data
    });
  });
};

const tokenToDelete = 'a-token';
const msgConfirmationDeletion = 'You are about to delete the token';
const irreversibleMsg =
  'This action cannot be undone. If you proceed, all requests made using this token will be rejected. Do you want to delete the token?';

describe('Api-token', () => {
  beforeEach(() => {
    i18next.use(initReactI18next).init({
      lng: 'en',
      resources: {}
    });
    const store = createStore();

    store.set(userAtom, {
      canManageApiTokens: true,
      isAdmin: true,
      locale: 'en_US',
      timezone: 'Europe/Paris'
    });

    interceptListTokens({});

    cy.mount({
      Component: (
        <Provider store={store}>
          <SnackbarProvider>
            <Router>
              <TestQueryProvider>
                <TokenListing id="cy-root" />
              </TestQueryProvider>
            </Router>
          </SnackbarProvider>
        </Provider>
      )
    });
  });

  it('displays all tokens when the page loads', () => {
    cy.waitForRequest('@getListTokens');

    cy.contains('a-token');
    cy.contains('b-token');

    cy.makeSnapshot();
  });

  it('displays the token creation button', () => {
    cy.findByTestId(labelCreateNewToken).contains(labelAdd);

    cy.makeSnapshot();
  });

  it('displays the modal when clicking on token creation button', () => {
    openDialog();

    cy.findByTestId('tokenName').contains(labelName);

    cy.findByTestId('tokenNameInput').should('have.attr', 'required');

    cy.findByTestId(labelDuration)
      .should('be.visible')
      .should('have.attr', 'required');

    cy.findByTestId(labelUser)
      .should('be.visible')
      .should('have.attr', 'required');

    cy.findByTestId(labelCancel).should('be.visible');
    cy.findByTestId('Confirm')
      .contains(labelGenerateNewToken)
      .should('be.visible')
      .should('be.disabled');

    cy.makeSnapshot();
    cy.findByTestId(labelCancel).click();
  });

  it('displays an updated Create token button that becomes enabled when the required inputs are filled in', () => {
    cy.fixture('apiTokens/creation/configuredUsers.json').then((data) => {
      cy.interceptAPIRequest({
        alias: 'getListConfiguredUsers',
        method: Method.GET,
        path: `./api/latest${listConfiguredUser}**`,
        response: data
      });
    });
    openDialog();

    cy.findByTestId('tokenNameInput').type(tokenName);
    cy.findByTestId('tokenNameInput').should('have.value', tokenName);

    cy.findByTestId(labelDuration).click();
    cy.findByRole('option', { name: duration.name })
      .should('be.visible')
      .click();
    cy.findByTestId(labelDuration).should('have.value', duration.name);

    cy.findByTestId(labelUser).click();
    cy.waitForRequest('@getListConfiguredUsers');

    cy.fixture('apiTokens/creation/configuredUsers.json').then(({ result }) => {
      cy.findByRole('option', { name: result[0].alias })
        .should('be.visible')
        .click();
      cy.findByTestId(labelUser).should('have.value', result[0].alias);
    });

    cy.findByTestId(labelCancel).should('be.visible');

    cy.findByTestId('Confirm')
      .contains(labelGenerateNewToken)
      .should('be.enabled');

    cy.makeSnapshot();
    cy.findByTestId(labelCancel).click();
  });

  it('displays an updated modal when generating a token', () => {
    openDialog();

    cy.fixture(
      'apiTokens/creation/generatedTokenWithDefaultDuration.json'
    ).then((data) => {
      cy.interceptAPIRequest({
        alias: 'createToken',
        method: Method.POST,
        path: `./api/latest${createTokenEndpoint}**`,
        response: data
      });
    });
    const now = new Date(2024, 1, 27, 18, 16, 33);

    cy.clock(now);

    fillInputs();

    cy.findByTestId(labelDuration).click();

    cy.findByRole('option', { name: 'Customize' }).click();
    cy.openCalendar('calendarInput');

    cy.findByRole('gridcell', { name: '29' }).click({
      waitForAnimations: false
    });
    cy.contains('OK').click({
      waitForAnimations: false
    });

    cy.findByTestId(labelDuration).should(
      'have.value',
      'February 29, 2024 7:16 PM'
    );

    cy.clock().then((clock) => {
      clock.restore();
    });

    cy.findByTestId('Confirm')
      .contains(labelGenerateNewToken)
      .should('be.enabled')
      .click();

    cy.waitForRequest('@createToken');

    cy.contains(labelTokenCreated);
    cy.contains(labelSecurityToken);

    cy.fixture(
      'apiTokens/creation/generatedTokenWithDefaultDuration.json'
    ).then((data) => {
      checkModalInformationWithGeneratedToken({
        data,
        durationValue: duration.name
      });
      checkTokenInput(data.token);
    });

    cy.makeSnapshot();
  });

  it('deletes the token when clicking on the Delete button', () => {
    cy.waitForRequest('@getListTokens');

    const deleteToken = deleteTokenEndpoint({
      tokenName: tokenToDelete,
      userId: 23
    });
    cy.interceptAPIRequest({
      alias: 'deleteToken',
      method: Method.DELETE,
      path: `./api/latest${deleteToken}**`,
      statusCode: 204
    });

    interceptListTokens({
      alias: 'getListTokensAfterDeletion',
      dataPath: 'apiTokens/listing/listAfterDelete.json'
    });

    cy.findAllByTestId('DeleteIcon')
      .eq(0)
      .parent()
      .should('be.enabled')
      .click();
    cy.findByTestId('deleteDialog').within(() => {
      cy.contains(labelDeleteToken);
      cy.contains(msgConfirmationDeletion);
      cy.contains(tokenToDelete);
      cy.contains(irreversibleMsg);

      cy.contains(labelCancel).should('be.enabled');
      cy.findByTestId('Confirm').should('be.enabled');
      cy.makeSnapshot('displays the modal when clicking the Delete icon');
      cy.findByTestId('Confirm').should('be.enabled').click();
      cy.waitForRequest('@deleteToken');
      cy.getRequestCalls('@deleteToken').then((calls) => {
        expect(calls).to.have.length(1);
      });
    });
    cy.contains(labelTokenDeletedSuccessfully);
    cy.waitForRequest('@getListTokensAfterDeletion');
    cy.findAllByTestId('deleteDialog').should('not.exist');
    cy.contains(tokenToDelete).should('not.exist');
    cy.makeSnapshot('deletes the token when clicking the Delete Button');
  });

  it('hides the modal when clicking on the Cancel button', () => {
    cy.waitForRequest('@getListTokens');

    cy.findAllByTestId('DeleteIcon')
      .eq(0)
      .parent()
      .should('be.enabled')
      .click();
    cy.findByTestId('deleteDialog').within(() => {
      cy.contains(labelDeleteToken);
      cy.contains(msgConfirmationDeletion);
      cy.contains(tokenToDelete);
      cy.contains(irreversibleMsg);
      cy.findByTestId('Confirm').should('be.enabled');
      cy.contains(labelCancel).should('be.enabled').click();
    });
    cy.findAllByTestId('deleteDialog').should('not.exist');
  });
});
