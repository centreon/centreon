import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Formik } from 'formik';
import { BrowserRouter } from 'react-router';
import { getTokensEndpoint } from '../../api/endpoints';
import { ConnectionMode } from '../../models';
import AgentInitiated from './AgentInitiated';

const initialValues = {
  connectionMode: {
    id: ConnectionMode.secure,
    name: 'TLS'
  },
  configuration: {
    otelPublicCertificate: '',
    otelCaCertificate: '',
    otelPrivateKey: '',
    tokens: [
      { name: 'testName', id: 1, inputValue: 'testInput' },
      { name: 'testName2', id: 2, inputValue: 'testInput2', testId: 'testId2' }
    ]
  }
};

const valuesWithErrors = {
  connectionMode: {
    id: ConnectionMode.secure,
    name: 'TLS'
  },
  configuration: {
    otelPublicCertificate: '',
    otelCaCertificate: '',
    otelPrivateKey: '',
    tokens: []
  }
};

const mockErrors = {
  connectionMode: {
    id: ConnectionMode.secure,
    name: 'TLS'
  },
  configuration: {
    otelPublicCertificate: 'Public certificate is required',
    otelCaCertificate: 'CA certificate is required',
    otelPrivateKey: 'Private key is required',
    tokens: 'At least one token is required'
  }
};

const mockTouched = {
  configuration: {
    otelPublicCertificate: true,
    otelCaCertificate: true,
    otelPrivateKey: true,
    tokens: true
  }
};

const initialize = (
  values = initialValues,
  errors = {},
  touched = {}
): void => {
  cy.mount({
    Component: (
      <BrowserRouter>
        <QueryClientProvider client={new QueryClient()}>
          <Formik
            initialValues={values}
            initialErrors={errors}
            initialTouched={touched}
            onSubmit={cy.stub()}
          >
            <AgentInitiated />
          </Formik>
        </QueryClientProvider>
      </BrowserRouter>
    )
  });
};

describe('AgentInitiated', () => {
  it('should render the component with initial values', () => {
    initialize();
    cy.get('[data-testid="Public certificate (.crt,.cer)"] input').should(
      'have.value',
      ''
    );
    cy.get('[data-testid="CA (.crt,.cer)"] input').should('have.value', '');
    cy.get('[data-testid="Private key (.key)"] input').should('have.value', '');
    cy.get('[data-testid="Select existing CMA token(s)"]').should('be.visible');

    cy.makeSnapshot();
  });

  it('should display error messages when fields are touched and have errors', () => {
    initialize(valuesWithErrors, mockErrors, mockTouched);

    cy.get('[data-testid="Public certificate (.crt,.cer)"]').should('exist');
    cy.get('[data-testid="CA (.crt,.cer)"]').should('exist');
    cy.get('[data-testid="Private key (.key)"]').should('exist');
    cy.get('[data-testid="Select existing CMA token(s)"]').should('exist');
  });

  it('should render with pre-filled certificate values', () => {
    const prefilledValues = {
      connectionMode: {
        id: ConnectionMode.secure,
        name: 'TLS'
      },
      configuration: {
        otelPublicCertificate: 'existing-public.crt',
        otelCaCertificate: 'existing-ca.crt',
        otelPrivateKey: 'existing-private.key',
        tokens: []
      }
    };

    initialize(prefilledValues);

    cy.get('[data-testid="Public certificate (.crt,.cer)"] input').should(
      'have.value',
      'existing-public.crt'
    );
    cy.get('[data-testid="CA (.crt,.cer)"] input').should(
      'have.value',
      'existing-ca.crt'
    );
    cy.get('[data-testid="Private key (.key)"] input').should(
      'have.value',
      'existing-private.key'
    );
  });

  it('should handle empty tokens array', () => {
    const emptyTokensValues = {
      configuration: {
        otelPublicCertificate: '',
        otelCaCertificate: '',
        otelPrivateKey: '',
        tokens: []
      }
    };

    initialize(emptyTokensValues);
    cy.get('[data-testid="Select existing CMA token(s)"]').should('be.visible');
  });

  it('should render tokens field as required', () => {
    initialize();
    cy.get('[data-testid="Select existing CMA token(s)"]')
      .parent()
      .should('contain', '*');
  });

  it('should have correct aria-labels for accessibility', () => {
    initialize();

    cy.get('[data-testid="Public certificate (.crt,.cer)"] input').should(
      'have.attr',
      'aria-label',
      'Public certificate (.crt,.cer)'
    );
    cy.get('[data-testid="CA (.crt,.cer)"] input').should(
      'have.attr',
      'aria-label',
      'CA (.crt,.cer)'
    );
    cy.get('[data-testid="Private key (.key)"] input').should(
      'have.attr',
      'aria-label',
      'Private key (.key)'
    );
  });

  it('should handle null values for certificates', () => {
    const nullValues = {
      connectionMode: {
        id: ConnectionMode.secure,
        name: 'TLS'
      },
      configuration: {
        otelPublicCertificate: null,
        otelCaCertificate: null,
        otelPrivateKey: null,
        tokens: null
      }
    };

    initialize(nullValues);

    cy.get('[data-testid="Public certificate (.crt,.cer)"] input').should(
      'have.value',
      ''
    );
    cy.get('[data-testid="CA (.crt,.cer)"] input').should('have.value', '');
    cy.get('[data-testid="Private key (.key)"] input').should('have.value', '');
  });

  it('should update the public certificate field', () => {
    initialize();
    cy.get('[data-testid="Public certificate (.crt,.cer)"] input')
      .clear()
      .type('test.crt');
    cy.get('[data-testid="Public certificate (.crt,.cer)"] input').should(
      'have.value',
      'test.crt'
    );
  });

  it('should update the CA certificate field', () => {
    initialize();
    cy.get('[data-testid="CA (.crt,.cer)"] input').clear().type('test_ca.crt');
    cy.get('[data-testid="CA (.crt,.cer)"] input').should(
      'have.value',
      'test_ca.crt'
    );
  });

  it('should update the private key field', () => {
    initialize();
    cy.get('[data-testid="Private key (.key)"] input').clear().type('test.key');
    cy.get('[data-testid="Private key (.key)"] input').should(
      'have.value',
      'test.key'
    );
  });

  it('should clear certificate fields when empty string is entered', () => {
    const prefilledValues = {
      connectionMode: {
        id: ConnectionMode.secure,
        name: 'TLS'
      },
      configuration: {
        otelPublicCertificate: 'existing-public.crt',
        otelCaCertificate: 'existing-ca.crt',
        otelPrivateKey: 'existing-private.key'
      }
    };

    initialize(prefilledValues);

    cy.get('[data-testid="Public certificate (.crt,.cer)"] input').clear();
    cy.get('[data-testid="Public certificate (.crt,.cer)"] input').should(
      'have.value',
      ''
    );
  });

  it('should handle CMA tokens selection and change', () => {
    initialize();
    cy.stub(getTokensEndpoint);

    cy.get('[data-testid="Select existing CMA token(s)"]').click();

    cy.get('[data-testid="Select existing CMA token(s)"]').type(
      'new-token{enter}'
    );
  });

  it('should handle token deletion', () => {
    const valuesWithTokens = {
      configuration: {
        otelPublicCertificate: '',
        otelCaCertificate: '',
        otelPrivateKey: '',
        tokens: [
          { name: 'token1', id: 1, inputValue: 'input1' },
          { name: 'token2', id: 2, inputValue: 'input2' }
        ]
      }
    };

    initialize(valuesWithTokens);

    cy.get('[data-testid="Select existing CMA token(s)"]').should('be.visible');

    cy.get('[data-testid="Select existing CMA token(s)"]')
      .get('[data-testid="CancelIcon"]')
      .first()
      .click();
  });

  it('should handle empty tokens when changing CMA tokens', () => {
    initialize();

    cy.get('[data-testid="Select existing CMA token(s)"]').click();

    cy.get('body').click();
  });

  it('should call changeCMATokens when token selection changes', () => {
    initialize();

    cy.get('[data-testid="Select existing CMA token(s)"]').click();

    cy.get('[data-testid="Select existing CMA token(s)"]').type('test-token');

    cy.get('[data-testid="Select existing CMA token(s)"]').clear();
  });

  it('should properly remove tokens when delete is clicked', () => {
    const valuesWithMultipleTokens = {
      configuration: {
        otelPublicCertificate: '',
        otelCaCertificate: '',
        otelPrivateKey: '',
        tokens: [
          { name: 'token1', id: 1, inputValue: 'input1' },
          { name: 'token2', id: 2, inputValue: 'input2' },
          { name: 'token3', id: 3, inputValue: 'input3' }
        ]
      }
    };

    initialize(valuesWithMultipleTokens);

    cy.get('[data-testid="tag-option-chip-1"]').should('contain', 'token1');
    cy.get('[data-testid="tag-option-chip-2"]').should('contain', 'token2');
    cy.get('[data-testid="tag-option-chip-3"]').should('contain', 'token3');

    cy.get('[data-testid="Select existing CMA token(s)"]')
      .get('[data-testid="CancelIcon"]')
      .first()
      .click();

    cy.get('[data-testid="tag-option-chip-2"]').should('contain', 'token2');
    cy.get('[data-testid="tag-option-chip-3"]').should('contain', 'token3');

    cy.get('[data-testid="Select existing CMA token(s)"]')
      .get('[data-testid="CancelIcon"]')
      .first()
      .click();
  });
});
