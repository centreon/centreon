import { renderHook } from '@testing-library/react-hooks/dom';
import dayjs from 'dayjs';
import LocalizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { useAtomValue } from 'jotai';
import { BrowserRouter as Router } from 'react-router-dom';
import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';

import {
  Method,
  SnackbarProvider,
  TestQueryProvider,
  useLocaleDateTimeFormat
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { DefaultParameters } from './TokenListing/Actions/Search/Filter/models';
import { Column } from './TokenListing/ComponentsColumn/models';
import TokenListing from './TokenListing/TokenListing';
import {
  buildListEndpoint,
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

const columns = [
  {
    id: 'status',
    label: Column.Status
  },
  {
    id: 'token_name',
    label: Column.Name
  },
  {
    id: 'creation_date',
    label: Column.CreationDate
  },
  {
    id: 'expiration_date',
    label: Column.ExpirationDate
  },
  {
    id: 'user_name',
    label: Column.User
  },
  {
    id: 'creator_name',
    label: Column.Creator
  },
  {
    id: 'actions',
    label: Column.Actions
  }
];

const checkInformationRow = (data): void => {
  const localDateTimeFormat = renderHook(() => useLocaleDateTimeFormat());

  const { format } = localDateTimeFormat.result.current;
  const formatString = 'L';

  const creationDate = format({
    date: data.creation_date,

    formatString
  });
  const expirationDate = format({
    date: data.expiration_date,

    formatString
  });

  cy.contains(data.name);
  cy.contains(expirationDate);
  cy.contains(creationDate);
  cy.contains(data.user.name);
  cy.contains(data.creator.name);
};

const checkArrowSorting = (data): void => {
  const idColumn = Object.keys(data.sort_by)[0];
  const columnName = columns.filter(({ id }) => id === idColumn)?.[0]?.label;

  cy.findByLabelText(`Column ${columnName}`)
    .findByTestId('ArrowDownwardIcon')
    .should('be.visible');
};

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

const interceptListTokens = ({
  dataPath = 'apiTokens/listing/list.json',
  parameters = DefaultParameters,
  alias = 'getListTokens',
  method = Method.GET
}): void => {
  cy.fixture(dataPath).then((data) => {
    const endpoint = buildListEndpoint({
      endpoint: listTokensEndpoint,
      parameters: { ...parameters }
    });
    cy.interceptAPIRequest({
      alias,
      method,
      path: `./api/latest${endpoint}**`,
      response: data
    });
  });
};

const defaultParameters = 'page=2&limit=10&sort_by={"token_name":"asc"}';
const firstPageParameter = 'page=1&limit=10';
const secondPageParameter = 'page=2&limit=10';
const customLimitParameters = 'page=1&limit=20';
const limits = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

const tokenToDelete = 'a-token';
const msgConfirmationDeletion = 'You are about to delete the token';
const irreversibleMsg =
  'This is an irreversible action. If you process, all requests made with this token will be rejected. Do you want to process anyway ?';

describe('Api-token', () => {
  beforeEach(() => {
    i18next.use(initReactI18next).init({
      lng: 'en',
      resources: {}
    });
    const userData = renderHook(() => useAtomValue(userAtom));

    userData.result.current.timezone = 'Europe/Paris';
    userData.result.current.locale = 'en_US';

    interceptListTokens({});

    cy.mount({
      Component: (
        <Router>
          <SnackbarProvider>
            <TestQueryProvider>
              <TokenListing />
            </TestQueryProvider>
          </SnackbarProvider>
        </Router>
      )
    });
  });

  it('displays all tokens when the page loads', () => {
    cy.waitForRequest('@getListTokens');

    cy.fixture('apiTokens/listing/list.json').then((data) => {
      cy.findByTestId('Listing Pagination').contains(data.meta.limit);
      cy.findByLabelText(`Previous page`).should('be.disabled');
      cy.findByLabelText(`Next page`).should('be.enabled');

      checkArrowSorting(data.meta);

      [...Array(data.meta.limit).keys()].forEach((key) => {
        checkInformationRow(data.result[key]);
      });
    });
    cy.makeSnapshot();
  });

  it('retrieves refreshed data with the same parameters when clicking on refresh icon button', () => {
    cy.waitForRequest('@getListTokens');
    cy.findByLabelText('Refresh').click();
    cy.waitForRequest('@getListTokens');
    cy.getRequestCalls('@getListTokens').then((calls) => {
      expect(calls).to.have.length(2);
      expect(calls[0].request.url.search.includes(defaultParameters));
    });

    cy.fixture('apiTokens/listing/list.json').then((data) => {
      checkArrowSorting(data.meta);
      checkInformationRow(data.result[0]);
    });
  });

  it('executes a listing request with an updated page param when a change page action is clicked', () => {
    cy.waitForRequest('@getListTokens');

    interceptListTokens({
      alias: 'getListTokensPage2',
      dataPath: 'apiTokens/listing/listPage2.json',
      parameters: { ...DefaultParameters, page: 2 }
    });

    cy.findByLabelText(`Next page`).should('be.enabled').click();

    cy.waitForRequest('@getListTokensPage2');

    cy.getRequestCalls('@getListTokensPage2').then((calls) => {
      expect(calls[0].request.url.search.includes(secondPageParameter));
    });

    interceptListTokens({
      alias: 'getListTokens',
      dataPath: 'apiTokens/listing/list.json',
      parameters: DefaultParameters
    });

    cy.findByLabelText(`Previous page`).should('be.enabled').click();

    cy.waitForRequest('@getListTokens');
    cy.getRequestCalls('@getListTokens').then((calls) => {
      expect(calls[0].request.url.search.includes(firstPageParameter));
    });

    interceptListTokens({
      alias: 'getListTokensPage2',
      dataPath: 'apiTokens/listing/listPage2.json',
      parameters: { ...DefaultParameters, page: 2 }
    });

    cy.findByLabelText(`Last page`).should('be.enabled').click();

    cy.waitForRequest('@getListTokensPage2');

    cy.getRequestCalls('@getListTokensPage2').then((calls) => {
      expect(calls[0].request.url.search.includes(secondPageParameter));
    });

    interceptListTokens({
      alias: 'getListTokens',
      dataPath: 'apiTokens/listing/list.json',
      parameters: DefaultParameters
    });

    cy.findByLabelText(`First page`).should('be.enabled').click();

    cy.waitForRequest('@getListTokens');

    cy.getRequestCalls('@getListTokens').then((calls) => {
      expect(calls[0].request.url.search.includes(firstPageParameter));
    });

    cy.findByTestId('Listing Pagination').contains(10).click();
    limits.forEach((limit) => {
      cy.contains(limit);
    });

    cy.findByRole('option', { name: limits[2].toString() }).click();

    cy.waitForRequest('@getListTokens');

    cy.getRequestCalls('@getListTokens').then((calls) => {
      expect(calls[0].request.url.search.includes(customLimitParameters));
    });
  });

  it('enables the addition and removal of columns in the table listing when the user selects or deselects options in the Add Columns menu', () => {
    cy.waitForRequest('@getListTokens');
    cy.findByLabelText('Add columns').click();

    columns.forEach(({ label }) => {
      cy.contains(label);
    });

    cy.findByRole('menuitem', { name: columns[0].label }).click();
    cy.findByRole('menuitem', { name: columns[1].label }).click();
    cy.findByRole('menuitem', { name: columns[2].label }).click();

    cy.findByLabelText(`Column ${columns[0].label}`).should('not.exist');
    cy.findByLabelText(`Column ${columns[1].label}`).should('not.exist');
    cy.findByLabelText(`Column ${columns[1].label}`).should('not.exist');

    cy.makeSnapshot();

    cy.findByText('Reset').click();
    cy.findByLabelText(`Column ${columns[0].label}`).should('exist');
    cy.findByLabelText(`Column ${columns[1].label}`).should('exist');
    cy.findByLabelText(`Column ${columns[1].label}`).should('exist');
  });
  it('displays the token creation button', () => {
    cy.findByTestId(labelCreateNewToken).contains(labelCreateNewToken);

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
      cy.findByRole('option', { name: result[0].name })
        .should('be.visible')
        .click();
      cy.findByTestId(labelUser).should('have.value', result[0].name);
    });

    cy.findByTestId(labelCancel).should('be.visible');

    cy.findByTestId('Confirm')
      .contains(labelGenerateNewToken)
      .should('be.enabled');

    cy.makeSnapshot();
    cy.findByTestId(labelCancel).click();
  });

  it('displays an updated modal when generating a token ', () => {
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

    fillInputs();

    cy.findByTestId(labelDuration).click();
    cy.findByRole('option', { name: duration.name }).click();

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
