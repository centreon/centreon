import { renderHook } from '@testing-library/react-hooks/dom';
import dayjs from 'dayjs';
import LocalizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import i18next from 'i18next';
import { Provider, createStore, useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router-dom';

import {
  ListingParameters,
  Method,
  QueryParameter,
  SnackbarProvider,
  TestQueryProvider,
  useLocaleDateTimeFormat
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { labelAdd } from '../Resources/translatedLabels';

import {
  DefaultParameters,
  Fields
} from './TokenListing/Actions/Filter/models';
import { translateWhiteSpaceToRegex } from './TokenListing/Actions/Search/utils';
import { Column } from './TokenListing/ComponentsColumn/models';
import TokenListing from './TokenListing/TokenListing';
import {
  buildListEndpoint,
  createTokenEndpoint,
  listConfiguredUser,
  listTokensEndpoint,
  tokenEndpoint
} from './api/endpoints';
import {
  labelActiveOrRevoked,
  labelActiveToken,
  labelCancel,
  labelClear,
  labelCreateNewToken,
  labelCreationDate,
  labelCreator,
  labelDeleteToken,
  labelDuration,
  labelExpirationDate,
  labelGenerateNewToken,
  labelName,
  labelRevokedToken,
  labelSearch,
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
  },
  {
    id: 'activate',
    label: Column.Activate
  }
];

const checkInformationRow = (data): void => {
  const userData = renderHook(() => useAtomValue(userAtom));
  userData.result.current.locale = 'en_US';
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

interface Query {
  name: string;
  value: string;
}
interface InterceptListTokens {
  alias: string;
  customQueryParameters?: Array<QueryParameter> | null;
  dataPath: string;
  method?: Method;
  parameters?: ListingParameters;
  query?: Query;
}

const interceptListTokens = ({
  dataPath = 'apiTokens/listing/list.json',
  parameters = DefaultParameters,
  alias = 'getListTokens',
  method = Method.GET,
  query
}: InterceptListTokens): void => {
  cy.fixture(dataPath).then((data) => {
    const endpoint = buildListEndpoint({
      endpoint: listTokensEndpoint,
      parameters
    });
    cy.interceptAPIRequest({
      alias,
      method,
      path: `./api/latest${endpoint}`,
      query,
      response: data
    });
  });
};

const defaultParameters = '?page=1&limit=10&sort_by={"token_name":"asc"}';
const secondPageParameter = '?page=2&limit=10&sort_by={"token_name":"asc"}';
const customLimitParameters = '?page=1&limit=20&sort_by={"token_name":"asc"}';
const limits = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
const parametersWithAllSearchableFields =
  '{"$and":[{"$or":[{"creation_date":{"$ge":"2024-02-20T16:04:33Z"}}]},{"$or":[{"creator.id":{"$rg":"1"}}]},{"$or":[{"creator.name":{"$rg":"paul"}}]},{"$or":[{"expiration_date":{"$le":"2024-03-28T16:04:33Z"}}]},{"$or":[{"is_revoked":{"$eq":false}}]},{"$or":[{"token_name":{"$rg":"token1"}},{"token_name":{"$rg":"test"}}]},{"$or":[{"user.id":{"$rg":"18"}},{"user.id":{"$rg":"17"}}]},{"$or":[{"user.name":{"$rg":"Guest"}},{"user.name":{"$rg":"User"}}]}]}';

const parametersWithSelectedFilters =
  '{"$and":[{"$or":[{"creation_date":{"$ge":"2024-02-20T15:16:33Z"}}]},{"$or":[{"expiration_date":{"$le":"2024-03-28T15:16:33Z"}}]},{"$or":[{"user.name":{"$rg":"User"}}]}]}';
const tokenToDelete = 'a-token';
const tokenToPatch = 'a-token';
const msgConfirmationDeletion = 'You are about to delete the token';
const irreversibleMsg =
  'This action cannot be undone. If you proceed, all requests made using this token will be rejected. Do you want to delete the token?';

const searchParametersWithAllSearchableFields = [
  {
    field: 'creation_date',
    values: {
      $ge: '2024-02-20T16:04:33Z'
    }
  },
  {
    field: 'creator.id',
    values: {
      $rg: '1'
    }
  },
  {
    field: 'creator.name',
    values: {
      $rg: 'paul'
    }
  },
  {
    field: 'expiration_date',
    values: {
      $le: '2024-03-28T16:04:33Z'
    }
  },
  {
    field: 'is_revoked',
    values: {
      $eq: false
    }
  },
  {
    field: 'token_name',
    values: {
      $rg: 'token1'
    }
  },
  {
    field: 'token_name',
    values: {
      $rg: 'test'
    }
  },
  {
    field: 'user.id',
    values: {
      $rg: '18'
    }
  },
  {
    field: 'user.id',
    values: {
      $rg: '17'
    }
  },
  {
    field: 'user.name',
    values: {
      $rg: 'Guest'
    }
  },
  {
    field: 'user.name',
    values: {
      $rg: 'User'
    }
  }
];

const searchParametersWithSelectedFields = [
  {
    field: 'creation_date',
    values: {
      $ge: '2024-02-20T15:16:33Z'
    }
  },
  {
    field: 'expiration_date',
    values: {
      $le: '2024-03-28T15:16:33Z'
    }
  },
  {
    field: 'user.name',
    values: {
      $rg: 'User'
    }
  }
];
const searchableFieldsValues = [
  {
    field: Fields.CreationDate,
    type: 'date',
    values: ['2024-02-20T16:04:33Z']
  },
  {
    field: Fields.ExpirationDate,
    type: 'date',
    values: ['2024-03-28T16:04:33Z']
  },
  { field: Fields.CreatorId, type: 'number', values: [1] },
  { field: Fields.UserId, type: 'number', values: [18, 17] },
  { field: Fields.UserName, type: 'string', values: ['Guest', 'User'] },
  {
    field: Fields.CreatorName,
    type: 'string',
    values: ['paul']
  },
  { field: Fields.TokenName, type: 'string', values: ['token1', 'test'] },
  { field: Fields.IsRevoked, type: 'boolean', values: ['false'] }
];

const constructSearchInput = (arr): string => {
  return arr
    .map(({ field, values }) => {
      const value = values
        .map((item) =>
          equals(typeof item, 'string')
            ? translateWhiteSpaceToRegex(item)
            : item
        )
        .join(',');

      return `${field}:${value}`;
    })
    .join(' ');
};

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

    cy.fixture('apiTokens/listing/list.json').then((data) => {
      cy.findByTestId('Listing Pagination').contains(data.meta.limit);
      cy.findByLabelText('Previous page').should('be.disabled');
      cy.findByLabelText('Next page').should('be.enabled');

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
      expect(decodeURIComponent(calls[0].request.url.search)).to.deep.equal(
        defaultParameters
      );
    });

    cy.clickOutside();

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

    cy.findByLabelText('Next page').should('be.enabled').click();

    cy.waitForRequest('@getListTokensPage2');

    cy.getRequestCalls('@getListTokensPage2').then((calls) => {
      expect(decodeURIComponent(calls[0].request.url.search)).to.deep.equal(
        secondPageParameter
      );
    });

    interceptListTokens({
      alias: 'getListTokens',
      dataPath: 'apiTokens/listing/list.json',
      parameters: DefaultParameters
    });

    cy.findByLabelText('Previous page').should('be.enabled').click();

    cy.waitForRequest('@getListTokens');
    cy.getRequestCalls('@getListTokens').then((calls) => {
      expect(decodeURIComponent(calls[0].request.url.search)).to.deep.equal(
        defaultParameters
      );
    });

    interceptListTokens({
      alias: 'getListTokensPage2',
      dataPath: 'apiTokens/listing/listPage2.json',
      parameters: { ...DefaultParameters, page: 2 }
    });

    interceptListTokens({
      alias: 'getExpandedListTokens',
      dataPath: 'apiTokens/listing/expandedList.json',
      parameters: { ...DefaultParameters, limit: 20 }
    });

    cy.findByLabelText('Last page').should('be.enabled').click();

    cy.waitForRequest('@getListTokensPage2');

    cy.getRequestCalls('@getListTokensPage2').then((calls) => {
      expect(decodeURIComponent(calls[0].request.url.search)).to.deep.equal(
        secondPageParameter
      );
    });

    interceptListTokens({
      alias: 'getListTokens',
      dataPath: 'apiTokens/listing/list.json',
      parameters: DefaultParameters
    });

    cy.findByLabelText('First page').should('be.enabled').click();

    cy.waitForRequest('@getListTokens');

    cy.getRequestCalls('@getListTokens').then((calls) => {
      expect(decodeURIComponent(calls[0].request.url.search)).to.deep.equal(
        defaultParameters
      );
    });

    cy.findByTestId('Listing Pagination').contains(10).click();
    limits.forEach((limit) => {
      cy.contains(limit);
    });

    cy.findByRole('option', { name: limits[1].toString() }).click();

    cy.waitForRequest('@getExpandedListTokens');

    cy.getRequestCalls('@getExpandedListTokens').then((calls) => {
      expect(decodeURIComponent(calls[0].request.url.search)).to.deep.equal(
        customLimitParameters
      );
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

  it('revokes an API token when Activate/Revoke token is clicked', () => {
    cy.waitForRequest('@getListTokens');
    const patchToken = tokenEndpoint({
      tokenName: tokenToPatch,
      userId: 23
    });
    interceptListTokens({
      alias: 'getListTokensAfterRevoke',
      dataPath: 'apiTokens/listing/listAfterRevoke.json'
    });
    cy.interceptAPIRequest({
      alias: 'patchToken',
      method: Method.PATCH,
      path: `./api/latest${patchToken}**`,
      statusCode: 204
    });
    cy.findAllByLabelText(labelActiveOrRevoked).eq(0).click();
    cy.waitForRequest('@patchToken');
    cy.findAllByLabelText(labelActiveOrRevoked).eq(0).should('not.be.checked');

    cy.makeSnapshot();
  });

  it('displays an error message upon failed revoking of a token', () => {
    cy.waitForRequest('@getListTokens');
    const patchToken = tokenEndpoint({
      tokenName: tokenToPatch,
      userId: 23
    });
    cy.interceptAPIRequest({
      alias: 'patchToken',
      method: Method.PATCH,
      path: `./api/latest${patchToken}**`,
      response: {
        message: 'internal server error'
      },
      statusCode: 500
    });

    cy.findAllByLabelText(labelActiveOrRevoked).eq(0).click();
    cy.waitForRequest('@patchToken');

    cy.findByText('internal server error').should('be.visible');

    cy.makeSnapshot();
  });

  it('deletes the token when clicking on the Delete button', () => {
    cy.waitForRequest('@getListTokens');

    const deleteToken = tokenEndpoint({
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

  it('executes a listing request with all searchable fields', () => {
    cy.waitForRequest('@getListTokens');
    interceptListTokens({
      alias: 'getListTokensWithSearchableFields',
      dataPath: 'apiTokens/listing/search/listWithAllSearchableFields.json',
      parameters: {
        ...DefaultParameters,
        search: { conditions: searchParametersWithAllSearchableFields }
      },
      query: { name: 'search', value: parametersWithAllSearchableFields }
    });

    const searchInput = constructSearchInput(searchableFieldsValues);

    cy.findByTestId('inputSearch').type(`${searchInput}{enter}`);

    cy.findByTestId('inputSearch').should(
      'have.value',
      'creation_date:2024-02-20T16:04:33Z expiration_date:2024-03-28T16:04:33Z creator.id:1 user.id:18,17 user.name:Guest,User creator.name:paul token_name:token1,test is_revoked:false'
    );

    cy.waitForRequest('@getListTokensWithSearchableFields');
    cy.getRequestCalls('@getListTokensWithSearchableFields').then((calls) => {
      expect(decodeURIComponent(calls[0].request.url.search)).to.deep.equal(
        `${defaultParameters}&search=${parametersWithAllSearchableFields}`
      );
    });

    searchableFieldsValues.forEach(({ field, values, type }) => {
      values.forEach((value) => {
        if (equals(type, 'date')) {
          cy.contains(
            equals(field, Fields.CreationDate) ? '02/20/2024' : '03/28/2024'
          );

          return;
        }
        if (!equals(type, 'string')) {
          return;
        }
        cy.contains(value);
      });
    });

    cy.makeSnapshot();
  });

  it('display the filter interface', () => {
    cy.waitForRequest('@getListTokens');
    cy.findByTestId('Filter options').click();
    cy.findByTestId(labelCreationDate).should('be.visible');
    cy.findByTestId(labelExpirationDate).should('be.visible');
    cy.findByTestId(labelUser).should('be.visible');
    cy.findByTestId(labelCreator).should('be.visible');
    cy.findByTestId(labelActiveToken).should('be.visible');
    cy.findByTestId(labelRevokedToken).should('be.visible');

    cy.makeSnapshot();
  });

  it('update the filter interface when changes are made to the search bar', () => {
    cy.waitForRequest('@getListTokens');

    const searchInput =
      'user.name:centreon-gorgone,Guest is_revoked:true creation_date:2024-02-27T16:30:52Z';

    cy.findByTestId('inputSearch').type(`${searchInput}`);

    cy.findByTestId('Filter options').click();
    cy.findAllByTestId('FilterContainer').as('filterInterface');

    cy.get('@filterInterface').contains('centreon-gorgone');

    cy.get('@filterInterface').contains('Guest');

    cy.get('input[id="Disabled tokens"]').should('be.checked');
    cy.findAllByTestId(labelCreationDate).should(
      'have.value',
      'February 27, 2024 5:30 PM'
    );

    cy.makeSnapshot();
  });

  it('display the date filters when Customize is selected', () => {
    cy.waitForRequest('@getListTokens');
    cy.findByTestId('Filter options').click();

    const now = new Date(2024, 1, 27, 18, 16, 33);

    cy.clock(now);

    const initialDate = '02/27/2024 07:16 PM';

    cy.findByTestId(labelCreationDate).click();
    cy.findByRole('option', { name: 'Customize' }).click();

    cy.findByTestId(`${labelCreationDate}-calendarContainer`).within(() => {
      cy.contains('Until');
      cy.findByTestId('calendarInput').should('have.value', initialDate);
    });

    cy.findByTestId(labelExpirationDate).click();
    cy.findByRole('option', { name: 'Customize' }).click();

    cy.findByTestId(`${labelExpirationDate}-calendarContainer`).within(() => {
      cy.contains('Until');
      cy.findByTestId('calendarInput').should('have.value', initialDate);
    });

    cy.makeSnapshot();
  });

  it('update the filter interface when applying custom date filters', () => {
    cy.waitForRequest('@getListTokens');
    cy.findByTestId('Filter options').click();

    const now = new Date(2024, 1, 27, 18, 16, 33);

    cy.clock(now);

    cy.findByTestId(labelCreationDate).click();
    cy.findByRole('option', { name: 'Customize' }).click();

    cy.openCalendar('calendarInput');

    cy.findByRole('gridcell', { name: '28' }).click();
    cy.contains('OK').click();

    cy.findByTestId(labelCreationDate).should(
      'have.value',
      'February 28, 2024 7:16 PM'
    );

    cy.findByTestId(labelExpirationDate).click();
    cy.findByRole('option', { name: 'Customize' }).click();

    cy.openCalendar('calendarInput');

    cy.findByRole('gridcell', { name: '1' }).click();
    cy.contains('OK').click();

    cy.findByTestId(labelExpirationDate).should(
      'have.value',
      'February 1, 2024 7:16 PM'
    );

    cy.makeSnapshot();
  });

  it('update the search bar when changes are made to the filter interface', () => {
    cy.waitForRequest('@getListTokens');

    cy.fixture('apiTokens/creation/configuredUsers.json').then((data) => {
      cy.interceptAPIRequest({
        alias: 'getListConfiguredUsers',
        method: Method.GET,
        path: `./api/latest${listConfiguredUser}**`,
        response: data
      });
    });
    const now = new Date(2024, 1, 27, 18, 16, 33);

    cy.clock(now);

    const {
      result: {
        current: { toIsoString }
      }
    } = renderHook(() => useLocaleDateTimeFormat());

    cy.findByTestId('Filter options').click();

    cy.findAllByTestId(labelRevokedToken).click();

    cy.findByTestId(labelCreator).click();
    cy.findByRole('option', { name: 'Jane Doe' }).click();

    cy.findByTestId(labelUser).click();

    cy.waitForRequest('@getListConfiguredUsers');

    cy.findByRole('option', { name: 'Guest' }).click();
    cy.findByRole('option', { name: 'centreon-gorgone' }).click();

    cy.findByTestId(labelCreationDate).click();
    cy.findByRole('option', { name: 'Customize' }).click();

    cy.openCalendar('calendarInput');

    cy.findByRole('gridcell', { name: '5' }).click();

    cy.contains('button', 'OK').click();

    cy.findByTestId(labelCreationDate).should(
      'have.value',
      'February 5, 2024 7:16 PM'
    );

    const expectedCreationDate = toIsoString(new Date(2024, 1, 5, 18, 16, 33));

    const expectedSearch = `is_revoked:true creator.name:${translateWhiteSpaceToRegex(
      'Jane Doe'
    )} user.name:Guest,centreon-gorgone creation_date:${expectedCreationDate}`;

    cy.findByTestId('inputSearch').should('have.value', expectedSearch);

    cy.makeSnapshot();
  });

  it('executes a listing request with the selected filters ', () => {
    cy.waitForRequest('@getListTokens');
    const now = new Date(2024, 1, 27, 15, 16, 33);
    const expirationDate = 'March 28, 2024 4:16 PM';
    const creationDate = 'February 20, 2024 4:16 PM';

    cy.clock(now);
    cy.fixture('apiTokens/creation/configuredUsers.json').then((data) => {
      cy.interceptAPIRequest({
        alias: 'getListConfiguredUsers',
        method: Method.GET,
        path: `./api/latest${listConfiguredUser}**`,
        response: data
      });
    });

    interceptListTokens({
      alias: 'getListTokensWithSelectedFilters',
      dataPath: 'apiTokens/listing/search/listWithSelectedFields.json',
      parameters: {
        ...DefaultParameters,
        search: { conditions: searchParametersWithSelectedFields }
      },
      query: { name: 'search', value: parametersWithSelectedFilters }
    });

    cy.findByTestId('Filter options').click();

    cy.findByTestId(labelUser).click();

    cy.waitForRequest('@getListConfiguredUsers');

    cy.findByRole('option', { name: 'User' }).click();

    cy.findByTestId(labelCreationDate).click();
    cy.findByRole('option', { name: 'Last 7 days' }).click();

    cy.findByTestId(labelCreationDate).should('have.value', creationDate);

    cy.findByTestId(labelExpirationDate).click();
    cy.findByRole('option', { name: 'In 30 days' }).click();

    cy.findByTestId(labelExpirationDate).should('have.value', expirationDate);

    cy.clock().then((clock) => {
      clock.restore();
    });

    cy.findByTestId('FilterContainer').findByTestId(labelSearch).click();
    cy.waitForRequest('@getListTokensWithSelectedFilters');

    cy.getRequestCalls('@getListTokensWithSelectedFilters').then((calls) => {
      expect(decodeURIComponent(calls[0].request.url.search)).to.deep.equal(
        `${defaultParameters}&search=${parametersWithSelectedFilters}`
      );
    });

    const expectedExpirationDate = '03/28/2024';
    const expectedCreationDate = '02/20/2024';

    cy.contains('User');
    cy.contains(expectedCreationDate);
    cy.contains(expectedExpirationDate);

    cy.makeSnapshot();
  });

  it('clear the selected filters when clicking on the clear button', () => {
    cy.waitForRequest('@getListTokens');
    cy.findByTestId('Filter options').click();

    cy.findByTestId(labelCreator).click();
    cy.findByRole('option', { name: 'Jane Doe' }).click();

    cy.findByTestId(labelExpirationDate).click();
    cy.findByRole('option', { name: 'In 60 days' }).click();

    cy.findByTestId(labelClear).click();

    cy.findByTestId(labelCreator).should('not.have.value');
    cy.findByTestId(labelExpirationDate).should('not.have.value');

    cy.findByTestId('inputSearch').should('not.have.value');
    cy.makeSnapshot();
  });
});
