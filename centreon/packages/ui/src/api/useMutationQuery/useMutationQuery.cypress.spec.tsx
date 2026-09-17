import SnackbarProvider from '../../Snackbar/SnackbarProvider';
import TestQueryProvider from '../TestQueryProvider';
import useMutationQuery, { Method } from '.';

const TestComponent = (props) => {
  const mutation = useMutationQuery({
    ...props,
    getEndpoint: () => '/endpoint'
  });

  return (
    <button
      onClick={() =>
        mutation.mutateAsync({ payload: { a: 'a', b: 2, c: ['arr', 'ay'] } })
      }
      type="button"
    >
      Send
    </button>
  );
};

const initialize = ({ mutationProps, isError = false }) => {
  cy.interceptAPIRequest({
    alias: 'mutateEndpoint',
    method: mutationProps.method,
    path: './api/latest/endpoint',
    response: isError
      ? {
          message: 'custom error message'
        }
      : undefined,
    statusCode: isError ? 400 : 204
  });

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <TestComponent {...mutationProps} />
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });
};

describe('useMutationQuery', () => {
  it('sends data to an endpoint', () => {
    initialize({
      mutationProps: {
        getEndpoint: () => '/endpoint',
        method: Method.POST
      }
    });

    cy.get('button').click();

    cy.waitForRequest('@mutateEndpoint').then(({ request }) => {
      expect(request.method).to.equal('POST');
      expect(request.body).to.deep.equal({ a: 'a', b: 2, c: ['arr', 'ay'] });
      expect(request.headers.get('content-type')).to.equal('application/json');
    });
  });

  it("shows an error from the API via the Snackbar and inside the browser's console when posting data to an endpoint", () => {
    initialize({
      isError: true,
      mutationProps: {
        getEndpoint: () => '/endpoint',
        method: Method.POST
      }
    });

    cy.get('button').click();

    cy.contains('custom error message').should('be.visible');
  });

  it('does not show any message via the Snackbar when the httpCodesBypassErrorSnackbar is passed when posting data to an API', () => {
    initialize({
      isError: true,
      mutationProps: {
        getEndpoint: () => '/endpoint',
        httpCodesBypassErrorSnackbar: [400],
        method: Method.POST
      }
    });

    cy.get('button').click();

    cy.contains('custom error message').should('not.exist');
  });
});
