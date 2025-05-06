import useMutationQuery, { Method } from '.';
import SnackbarProvider from '../../Snackbar/SnackbarProvider';
import TestQueryProvider from '../TestQueryProvider';

// biome-ignore lint/suspicious/noImplicitAnyLet: <explanation>
let spyMutation;

const TestComponent = (props) => {
  const mutation = useMutationQuery({
    ...props,
    getEndpoint: () => '/endpoint'
  });

  spyMutation = mutation;

  return (
    <button
      type="button"
      onClick={() =>
        mutation.mutateAsync({ payload: { a: 'a', b: 2, c: ['arr', 'ay'] } })
      }
    >
      Send
    </button>
  );
};

const initialize = ({ mutationProps, isError = false }) => {
  cy.interceptAPIRequest({
    alias: 'mutateEndpoint',
    path: './api/latest/endpoint',
    statusCode: isError ? 400 : 204,
    method: mutationProps.method,
    response: isError
      ? {
          message: 'custom error message'
        }
      : undefined
  });

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <TestComponent {...mutationProps} />
        </TestQueryProvider>
      </SnackbarProvider>
    )
  }).then(() => {
    cy.spy(spyMutation, 'mutateAsync').as('mutateAsync');
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
    cy.get('@mutateAsync').should('be.calledWith', {
      payload: {
        a: 'a',
        b: 2,
        c: ['arr', 'ay']
      }
    });
  });

  it("shows an error from the API via the Snackbar and inside the browser's console when posting data to an endpoint", () => {
    initialize({
      mutationProps: {
        getEndpoint: () => '/endpoint',
        method: Method.POST
      },
      isError: true
    });

    cy.get('button').click();

    cy.get('@mutateAsync').should('be.called');

    cy.contains('custom error message').should('be.visible');
  });

  it('does not show any message via the Snackbar when the httpCodesBypassErrorSnackbar is passed when posting data to an API', () => {
    initialize({
      mutationProps: {
        getEndpoint: () => '/endpoint',
        method: Method.POST,
        httpCodesBypassErrorSnackbar: [400]
      },
      isError: true
    });

    cy.get('button').click();

    cy.get('@mutateAsync').should('be.called');

    cy.contains('custom error message').should('not.exist');
  });
});
