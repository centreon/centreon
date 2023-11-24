import { renderHook } from '@testing-library/react-hooks/dom';
import dayjs from 'dayjs';
import LocalizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { Provider, createStore, useAtomValue } from 'jotai';
import { BrowserRouter as Router } from 'react-router-dom';

import {
  Method,
  TestQueryProvider,
  useLocaleDateTimeFormat
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { buildListTokensEndpoint } from '../api/endpoints';

import Listing from './Listing';
import { DefaultParameters } from './Actions/Search/Filter/models';
import { Columns } from './ComponentsColumn/models';

dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(LocalizedFormat);

const columns = [
  {
    id: 'status',
    label: Columns.status
  },
  {
    id: 'token_name',
    label: Columns.name
  },
  {
    id: 'creation_date',
    label: Columns.creationDate
  },
  {
    id: 'expiration_date',
    label: Columns.expirationDate
  },
  {
    id: 'user_name',
    label: Columns.user
  },
  {
    id: 'creator_name',
    label: Columns.creator
  },
  {
    id: 'actions',
    label: Columns.actions
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

const interceptListTokens = ({
  dataPath = 'appTokens/list.json',
  parameters = DefaultParameters,
  alias = 'getListTokens'
}): void => {
  cy.fixture(dataPath).then((data) => {
    const endpoint = buildListTokensEndpoint({
      parameters: { ...parameters }
    });
    cy.interceptAPIRequest({
      alias,
      method: Method.GET,
      path: endpoint,
      response: data
    });
  });
};

const defaultParameters = 'page=2&limit=10&sort_by={"token_name":"asc"}';
const firstPageParameter = 'page=1&limit=10';
const secondPageParameter = 'page=2&limit=10';
const customLimitParameters = 'page=1&limit=20';
const limits = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

const screenResolutions = [
  {
    'ipad-2': { height: 768, width: 1024 }
  },
  {
    'macbook-13': { height: 1280, width: 800 }
  },
  {
    'macbook-16': { height: 1536, width: 960 }
  },
  {
    'samsung-s10': { height: 360, width: 760 }
  }
];

describe('App-token listing', () => {
  beforeEach(() => {
    cy.viewport('macbook-13');

    const store = createStore();

    const userData = renderHook(() => useAtomValue(userAtom));

    userData.result.current.timezone = 'Europe/Paris';
    userData.result.current.locale = 'en_US';

    interceptListTokens({});

    cy.mount({
      Component: (
        <Router>
          <Provider store={store}>
            <TestQueryProvider>
              <Listing />
            </TestQueryProvider>
          </Provider>
        </Router>
      )
    });
  });

  it('displays all tokens when the page loads', () => {
    cy.waitForRequest('@getListTokens');

    cy.fixture('appTokens/list.json').then((data) => {
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

    cy.fixture('appTokens/list.json').then((data) => {
      checkArrowSorting(data.meta);
      checkInformationRow(data.result[0]);
    });
  });

  it('executes a listing request with an updated page param when a change page action is clicked', () => {
    cy.waitForRequest('@getListTokens');

    interceptListTokens({
      alias: 'getListTokensPage2',
      dataPath: 'appTokens/listPage2.json',
      parameters: { ...DefaultParameters, page: 2 }
    });

    cy.findByLabelText(`Next page`).should('be.enabled').click();

    cy.waitForRequest('@getListTokensPage2');

    cy.getRequestCalls('@getListTokensPage2').then((calls) => {
      expect(calls[0].request.url.search.includes(secondPageParameter));
    });

    interceptListTokens({
      alias: 'getListTokens',
      dataPath: 'appTokens/list.json',
      parameters: DefaultParameters
    });

    cy.findByLabelText(`Previous page`).should('be.enabled').click();

    cy.waitForRequest('@getListTokens');
    cy.getRequestCalls('@getListTokens').then((calls) => {
      expect(calls[0].request.url.search.includes(firstPageParameter));
    });

    interceptListTokens({
      alias: 'getListTokensPage2',
      dataPath: 'appTokens/listPage2.json',
      parameters: { ...DefaultParameters, page: 2 }
    });

    cy.findByLabelText(`Last page`).should('be.enabled').click();

    cy.waitForRequest('@getListTokensPage2');

    cy.getRequestCalls('@getListTokensPage2').then((calls) => {
      expect(calls[0].request.url.search.includes(secondPageParameter));
    });

    interceptListTokens({
      alias: 'getListTokens',
      dataPath: 'appTokens/list.json',
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

  screenResolutions.forEach((element) => {
    const { width, height } = Object.values(element)[0];
    it(`displays correctly the actions listing design when screen resolution is ${width}px`, () => {
      cy.viewport(height, width);
      cy.waitForRequest('@getListTokens');
      cy.makeSnapshot(`${width}px`);
    });
  });

  it('enable the addition and removal of columns in the table listing when users select or deselect options in the Add Columns menu', () => {
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
});
