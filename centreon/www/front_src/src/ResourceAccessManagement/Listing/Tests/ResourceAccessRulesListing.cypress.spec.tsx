import { equals } from 'ramda';
import { createStore, Provider } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import ResourceAccessRulesListing from '../Listing';
import { buildResourceAccessRulesEndpoint } from '../api/endpoints';
import { resourceAccessRuleEndpoint } from '../../AddEditResourceAccessRule/api/endpoints';
import {
  labelActiveOrInactive,
  labelCancel,
  labelDelete,
  labelDeleteMultipleResourceAccessRules,
  labelDeleteResourceAccessRule,
  labelDeleteResourceAccessRuleDialogMessage,
  labelDeleteResourceAccessRules,
  labelDeleteResourceAccessRulesDialogMessage,
  labelDeleteResourceAccessRulesWarning,
  labelDeleteResourceAccessRuleWarning,
  labelFailedToDeleteSelectedRules,
  labelResourceAccessRuleDeletedSuccess,
  labelResourceAccessRulesDeletedSuccess
} from '../../translatedLabels';
import { DeleteConfirmationDialog } from '../../Actions/Delete';

import {
  defaultQueryParams,
  deleteMultipleRulesFailedResponse,
  deleteMultipleRulesSuccessResponse,
  deleteMultipleRulesWarningResponse,
  getListingColumns,
  getListingResponse
} from './testUtils';

const store = createStore();

const ListingWithQueryProvider = (): JSX.Element => {
  return (
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <TestQueryProvider>
          <SnackbarProvider>
            <>
              <ResourceAccessRulesListing />
              <DeleteConfirmationDialog />
            </>
          </SnackbarProvider>
        </TestQueryProvider>
      </Provider>
    </div>
  );
};

const initialize = (): void => {
  cy.interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: buildResourceAccessRulesEndpoint(defaultQueryParams),
    response: getListingResponse({})
  });

  cy.interceptAPIRequest({
    alias: 'secondPageRequest',
    method: Method.GET,
    path: buildResourceAccessRulesEndpoint({
      ...defaultQueryParams,
      page: 2
    }),
    query: { name: 'page', value: '2' },
    response: getListingResponse({ page: 2 })
  });

  cy.interceptAPIRequest({
    alias: 'lastPageRequest',
    method: Method.GET,
    path: buildResourceAccessRulesEndpoint({
      ...defaultQueryParams,
      page: 7
    }),
    query: { name: 'page', value: '7' },
    response: getListingResponse({ page: 7 })
  });

  cy.interceptAPIRequest({
    alias: 'listingWithLimit',
    method: Method.GET,
    path: buildResourceAccessRulesEndpoint({
      ...defaultQueryParams,
      limit: 20
    }),
    query: { name: 'limit', value: '20' },
    response: getListingResponse({ limit: 20 })
  });

  cy.viewport('macbook-13');
  cy.mount({
    Component: <ListingWithQueryProvider />
  });
};

const columnToSort = getListingColumns()
  .filter(({ sortable }) => equals(sortable, true))
  .filter(({ id }) => id !== 'name');

const initializeSorting = (): void => {
  cy.interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: buildResourceAccessRulesEndpoint(defaultQueryParams),
    response: getListingResponse({})
  });

  columnToSort.forEach(({ id, label, sortField }) => {
    const sortBy = (sortField || id) as string;

    const requestEndpointDesc = buildResourceAccessRulesEndpoint({
      ...defaultQueryParams,
      sort: {
        [sortBy]: 'desc'
      }
    });

    cy.interceptAPIRequest({
      alias: `dataToListingTableDesc${label}`,
      method: Method.GET,
      path: requestEndpointDesc,
      response: getListingResponse({})
    });

    const requestEndpointAsc = buildResourceAccessRulesEndpoint({
      ...defaultQueryParams,
      sort: {
        [sortBy]: 'asc'
      }
    });

    cy.interceptAPIRequest({
      alias: `dataToListingTableAsc${label}`,
      method: Method.GET,
      path: requestEndpointAsc,
      response: getListingResponse({})
    });
  });

  cy.render(ListingWithQueryProvider);
};

describe('Resource Access Rules Listing', () => {
  beforeEach(initialize);

  it('displays the first page of the resource access rules listing', () => {
    cy.waitForRequest('@defaultRequest');

    cy.contains('rule1').should('be.visible');

    cy.makeSnapshot();
  });

  it('executes a get resource access rules request after updating limit param', () => {
    cy.waitForRequest('@defaultRequest');

    cy.get('#Rows\\ per\\ page').click();
    cy.contains(/^20$/).click();

    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'limit', value: '20' }],
      requestAlias: 'listingWithLimit'
    });

    cy.get('#Rows\\ per\\ page').click();
    cy.contains(/^10$/).click();

    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'limit', value: '10' }],
      requestAlias: 'defaultRequest'
    });

    cy.contains('rule1').should('be.visible');

    cy.makeSnapshot();
  });

  it('executes a listing request with an updated page parameter when change page action is clicked', () => {
    cy.waitForRequest('@defaultRequest');

    cy.findByLabelText('Next page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '2' }],
      requestAlias: 'secondPageRequest'
    });

    cy.findByLabelText('Previous page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '1' }],
      requestAlias: 'defaultRequest'
    });

    cy.findByLabelText('Last page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '7' }],
      requestAlias: 'lastPageRequest'
    });

    cy.findByLabelText('First page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '1' }],
      requestAlias: 'defaultRequest'
    });

    cy.contains('rule1').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('column sorting', () => {
  beforeEach(initializeSorting);

  it('executes a listing request when a sortable column is clicked', () => {
    cy.waitForRequest('@defaultRequest');

    columnToSort.forEach(({ label, id, sortField }) => {
      const sortBy = (sortField || id) as string;

      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'desc' } }],
        requestAlias: `dataToListingTableDesc${label}`
      });

      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'asc' } }],
        requestAlias: `dataToListingTableAsc${label}`
      });

      cy.contains('rule1').should('be.visible');

      cy.makeSnapshot(
        `column sorting --  executes a listing request when ${label} column is clicked`
      );
    });
  });
});

describe('Listing row actions: Delete button', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'defaultRequest',
      method: Method.GET,
      path: buildResourceAccessRulesEndpoint(defaultQueryParams),
      response: getListingResponse({})
    });

    cy.interceptAPIRequest({
      alias: 'deleteResourceAccessRuleRequest',
      method: Method.DELETE,
      path: resourceAccessRuleEndpoint({ id: 1 }),
      response: undefined,
      statusCode: 204
    });

    cy.render(ListingWithQueryProvider);
  });

  it("displays a confirmation dialog containing the resource access rule's name upon clicking on Delete button in rule listing", () => {
    cy.waitForRequest('@defaultRequest');

    const message = `rule0 ${labelDeleteResourceAccessRuleDialogMessage}`;
    cy.findAllByTestId(labelDeleteResourceAccessRule).eq(0).click();
    cy.findByText(labelDeleteResourceAccessRule).should('be.visible');
    cy.findByText(message).should('be.visible');
    cy.findByText(labelDeleteResourceAccessRuleWarning).should('be.visible');
    cy.findByTestId(labelCancel).should('be.visible');
    cy.findByLabelText(labelDelete).should('be.visible');

    cy.makeSnapshot();
    cy.findByTestId(labelCancel).click();
  });

  it('closes a delete confirmation dialog when Cancel button is clicked', () => {
    cy.waitForRequest('@defaultRequest');

    const message = `rule0 ${labelDeleteResourceAccessRuleDialogMessage}`;
    cy.findAllByTestId(labelDeleteResourceAccessRule).eq(0).click();
    cy.findByText(labelDeleteResourceAccessRule).should('be.visible');
    cy.findByText(message).should('be.visible');
    cy.findByText(labelDeleteResourceAccessRuleWarning).should('be.visible');
    cy.findByTestId(labelCancel).should('be.visible');
    cy.findByLabelText(labelDelete).should('be.visible');

    cy.findByTestId(labelCancel).click();
    cy.findByText(labelDeleteResourceAccessRule).should('not.exist');
    cy.findByText(message).should('not.exist');
    cy.findByText(labelDeleteResourceAccessRuleWarning).should('not.exist');
    cy.findByTestId(labelCancel).should('not.exist');
    cy.findByLabelText(labelDelete).should('not.exist');

    cy.makeSnapshot();
  });

  it('displays a success message after successful deletion', () => {
    cy.waitForRequest('@defaultRequest');

    cy.findAllByTestId(labelDeleteResourceAccessRule).eq(0).click();
    cy.findByLabelText(labelDelete).click();

    cy.waitForRequest('@deleteResourceAccessRuleRequest');
    cy.waitForRequest('@defaultRequest');

    cy.findByText(labelResourceAccessRuleDeletedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays an error message upon failed deletion', () => {
    cy.interceptAPIRequest({
      alias: 'deleteResourceAccessRuleFailedRequest',
      method: Method.DELETE,
      path: resourceAccessRuleEndpoint({ id: 1 }),
      response: {
        message: 'internal server error'
      },
      statusCode: 500
    });

    cy.waitForRequest('@defaultRequest');

    cy.findAllByTestId(labelDeleteResourceAccessRule).eq(0).click();
    cy.findByLabelText(labelDelete).click();
    cy.waitForRequest('@deleteResourceAccessRuleFailedRequest');

    cy.findByText('internal server error').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Listing row actions enable-disable action', () => {
  it('displays an error message upon failed disabling', () => {
    cy.interceptAPIRequest({
      alias: 'defaultRequest',
      method: Method.GET,
      path: buildResourceAccessRulesEndpoint(defaultQueryParams),
      response: getListingResponse({})
    });

    cy.interceptAPIRequest({
      alias: 'activateRuleRequest',
      method: Method.PATCH,
      path: resourceAccessRuleEndpoint({ id: 1 }),
      response: {
        message: 'internal server error'
      },
      statusCode: 500
    });

    cy.render(ListingWithQueryProvider);

    cy.waitForRequest('@defaultRequest');

    cy.findAllByLabelText(labelActiveOrInactive).eq(0).click();
    cy.waitForRequest('@activateRuleRequest');

    cy.findByText('internal server error').should('be.visible');

    cy.makeSnapshot();
  });
});

describe('Listing header actions: mass delete', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'defaultRequest',
      method: Method.GET,
      path: buildResourceAccessRulesEndpoint(defaultQueryParams),
      response: getListingResponse({})
    });

    cy.render(ListingWithQueryProvider);
  });

  it('confirms that multiple delete button is disabled when no resource access rule is selected and it becomes active once at least one row is selected', () => {
    cy.waitForRequest('@defaultRequest');

    cy.findByTestId(labelDeleteMultipleResourceAccessRules).should(
      'be.disabled'
    );
    cy.findByLabelText('Select row 1').click();
    cy.findByLabelText('Select row 2').click();
    cy.findByLabelText('Select row 3').click();
    cy.findByTestId(labelDeleteMultipleResourceAccessRules).should(
      'be.not.disabled'
    );

    cy.makeSnapshot();
  });

  it('displays a confirmation dialog when multiple delete button is clicked', () => {
    cy.waitForRequest('@defaultRequest');

    cy.findByTestId(labelDeleteMultipleResourceAccessRules).click();

    cy.findByText(labelDeleteResourceAccessRules).should('be.visible');
    cy.findByText(labelDeleteResourceAccessRulesDialogMessage).should(
      'be.visible'
    );
    cy.findByText(labelDeleteResourceAccessRulesWarning).should('be.visible');
    cy.findByText(labelDelete).should('be.visible');
    cy.findByText(labelCancel).should('be.visible');

    cy.makeSnapshot();

    cy.findByText(labelCancel).click();
  });

  it('displays a success message when all selected rules are deleted', () => {
    cy.interceptAPIRequest({
      alias: 'deleteResourceAccessRulesRequest',
      method: Method.POST,
      path: `${resourceAccessRuleEndpoint({})}/_delete`,
      response: deleteMultipleRulesSuccessResponse,
      statusCode: 207
    });

    cy.waitForRequest('@defaultRequest');
    cy.findByTestId(labelDeleteMultipleResourceAccessRules).click();
    cy.findByText(labelDelete).click();

    cy.waitForRequest('@deleteResourceAccessRulesRequest');
    cy.findByText(labelResourceAccessRulesDeletedSuccess).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays a warning message containing the list of names that failed to delete', () => {
    cy.interceptAPIRequest({
      alias: 'deleteResourceAccessRulesRequest',
      method: Method.POST,
      path: `${resourceAccessRuleEndpoint({})}/_delete`,
      response: deleteMultipleRulesWarningResponse,
      statusCode: 207
    });
    const warningMessage = `${labelFailedToDeleteSelectedRules}: rule0, rule1`;

    cy.waitForRequest('@defaultRequest');
    cy.findByLabelText('Select row 1').click();
    cy.findByLabelText('Select row 2').click();
    cy.findByLabelText('Select row 3').click();
    cy.findByTestId(labelDeleteMultipleResourceAccessRules).click();
    cy.findByText(labelDelete).click();

    cy.waitForRequest('@deleteResourceAccessRulesRequest');
    cy.waitForRequest('@defaultRequest');
    cy.findByText(warningMessage).should('be.visible');

    cy.makeSnapshot();
  });

  it('displays an error message when the deletion of all rules fails', () => {
    cy.interceptAPIRequest({
      alias: 'deleteResourceAccessRulesRequest',
      method: Method.POST,
      path: `${resourceAccessRuleEndpoint({})}/_delete`,
      response: deleteMultipleRulesFailedResponse,
      statusCode: 207
    });

    cy.waitForRequest('@defaultRequest');
    cy.findByLabelText('Select row 1').click();
    cy.findByLabelText('Select row 2').click();
    cy.findByLabelText('Select row 3').click();
    cy.findByTestId(labelDeleteMultipleResourceAccessRules).click();
    cy.findByText(labelDelete).click();

    cy.waitForRequest('@deleteResourceAccessRulesRequest');
    cy.findByText(labelFailedToDeleteSelectedRules).should('be.visible');

    cy.makeSnapshot();
  });
});
