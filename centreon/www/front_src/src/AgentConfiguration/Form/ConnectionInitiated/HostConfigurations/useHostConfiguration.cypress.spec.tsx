import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Formik } from 'formik';
import { BrowserRouter } from 'react-router';

import { AgentConfigurationForm, ConnectionMode } from '../../../models';
import { useHostConfiguration } from './useHostConfiguration';

const TestComponent = ({ index }: { index: number }) => {
  const {
    selectHost,
    changeAddress,
    changePort,
    changeStringInput,
    hostErrors,
    hostTouched,
    areCertificateFieldsVisible,
    changeCMAToken,
    token
  } = useHostConfiguration({ index });

  return (
    <div>
      <div data-testid="hook-results">
        <div data-testid="has-errors">{hostErrors ? 'true' : 'false'}</div>
        <div data-testid="has-touched">{hostTouched ? 'true' : 'false'}</div>
        <div data-testid="certificate-fields-visible">
          {areCertificateFieldsVisible ? 'true' : 'false'}
        </div>
        <div data-testid="token">{token ? JSON.stringify(token) : 'null'}</div>
      </div>

      {/* Test inputs */}
      <input
        data-testid="address-input"
        onChange={changeAddress}
        placeholder="Address"
      />
      <input
        data-testid="port-input"
        type="number"
        onChange={(e) => changePort(Number(e.target.value))}
        placeholder="Port"
      />
      <input
        data-testid="string-input"
        onChange={changeStringInput('pollerCaName')}
        placeholder="CA Name"
      />

      {/* Test buttons */}
      <button
        type="button"
        data-testid="select-host-button"
        onClick={() =>
          selectHost(null, { id: 1, name: 'test-host', address: '192.168.1.1' })
        }
      >
        Select Host
      </button>
      <button
        type="button"
        data-testid="change-token-button"
        onClick={() =>
          changeCMAToken(null, [{ id: 'token1', name: 'Token 1' }])
        }
      >
        Change Token
      </button>
    </div>
  );
};

const TestWrapper = ({
  children,
  initialValues,
  errors = {},
  touched = {}
}: {
  children: React.ReactNode;
  initialValues: AgentConfigurationForm;
  errors?: Record<string, string>;
  touched?: Record<string, boolean>;
}) => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        refetchOnWindowFocus: false,
        staleTime: 0,
        gcTime: 0
      }
    }
  });

  return (
    <BrowserRouter>
      <QueryClientProvider client={queryClient}>
        <Formik
          initialValues={initialValues}
          initialErrors={errors}
          initialTouched={touched}
          onSubmit={cy.stub()}
          validate={() => ({})}
        >
          {children}
        </Formik>
      </QueryClientProvider>
    </BrowserRouter>
  );
};

const defaultFormValues: AgentConfigurationForm = {
  name: 'test-config',
  type: { id: 'centreon-agent', name: 'Centreon Agent' },
  pollers: [{ id: 1, name: 'Central' }],
  connectionMode: { id: ConnectionMode.secure, name: 'Secure' },
  configuration: {
    agentInitiated: false,
    pollerInitiated: true,
    otelPublicCertificate: '',
    otelCaCertificate: '',
    otelPrivateKey: '',
    hosts: [
      {
        id: 0,
        name: '',
        address: '',
        port: 4317,
        pollerCaCertificate: '',
        pollerCaName: '',
        token: null
      }
    ]
  }
};

describe('useHostConfiguration', () => {
  it('render with default values', () => {
    cy.mount({
      Component: (
        <TestWrapper initialValues={defaultFormValues}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="has-errors"]').should('contain', 'false');
    cy.get('[data-testid="has-touched"]').should('contain', 'false');
    cy.get('[data-testid="certificate-fields-visible"]').should(
      'contain',
      'true'
    );
    cy.get('[data-testid="token"]').should('contain', 'null');
  });

  it('show certificate fields when connection mode is secure', () => {
    const secureFormValues = {
      ...defaultFormValues,
      connectionMode: { id: ConnectionMode.secure, name: 'Secure' }
    };

    cy.mount({
      Component: (
        <TestWrapper initialValues={secureFormValues}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="certificate-fields-visible"]').should(
      'contain',
      'true'
    );
  });

  it('show certificate fields when connection mode is insecure', () => {
    const insecureFormValues = {
      ...defaultFormValues,
      connectionMode: { id: ConnectionMode.insecure, name: 'Insecure' }
    };

    cy.mount({
      Component: (
        <TestWrapper initialValues={insecureFormValues}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="certificate-fields-visible"]').should(
      'contain',
      'true'
    );
  });

  it('hide certificate fields when connection mode is no-tls', () => {
    const noTlsFormValues = {
      ...defaultFormValues,
      connectionMode: { id: ConnectionMode.noTLS, name: 'No TLS' }
    };

    cy.mount({
      Component: (
        <TestWrapper initialValues={noTlsFormValues}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="certificate-fields-visible"]').should(
      'contain',
      'false'
    );
  });

  it('handle host selection', () => {
    cy.mount({
      Component: (
        <TestWrapper initialValues={defaultFormValues}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="select-host-button"]').click();

    cy.get('[data-testid="address-input"]').should('exist');
  });

  it('handle address change', () => {
    cy.mount({
      Component: (
        <TestWrapper initialValues={defaultFormValues}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="address-input"]').type('192.168.1.100');
    cy.get('[data-testid="address-input"]').should(
      'have.value',
      '192.168.1.100'
    );
  });

  it('handle port change', () => {
    cy.mount({
      Component: (
        <TestWrapper initialValues={defaultFormValues}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="port-input"]').type('8080');
    cy.get('[data-testid="port-input"]').should('have.value', '8080');
  });

  it('handle string input change', () => {
    cy.mount({
      Component: (
        <TestWrapper initialValues={defaultFormValues}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="string-input"]').type('test-ca-name');
    cy.get('[data-testid="string-input"]').should('have.value', 'test-ca-name');
  });

  it('handle token change', () => {
    cy.mount({
      Component: (
        <TestWrapper initialValues={defaultFormValues}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="change-token-button"]').click();
  });

  it('display errors when present', () => {
    const errorsObject = {
      configuration: {
        hosts: [
          {
            address: 'Address is required',
            port: 'Port is required'
          }
        ]
      }
    };

    cy.mount({
      Component: (
        <TestWrapper initialValues={defaultFormValues} errors={errorsObject}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="has-errors"]').should('contain', 'true');
  });

  it('display touched state when present', () => {
    const touchedObject = {
      configuration: {
        hosts: [
          {
            address: true,
            port: true
          }
        ]
      }
    };

    cy.mount({
      Component: (
        <TestWrapper initialValues={defaultFormValues} touched={touchedObject}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="has-touched"]').should('contain', 'true');
  });

  it('handle address with port format', () => {
    cy.mount({
      Component: (
        <TestWrapper initialValues={defaultFormValues}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="address-input"]').type('192.168.1.1:8080');

    cy.get('[data-testid="address-input"]').should('exist');
  });

  it('display token when present', () => {
    const formValuesWithToken = {
      ...defaultFormValues,
      configuration: {
        ...defaultFormValues.configuration,
        hosts: [
          {
            ...defaultFormValues.configuration.hosts[0],
            token: { id: 'token1', name: 'Test Token' }
          }
        ]
      }
    };

    cy.mount({
      Component: (
        <TestWrapper initialValues={formValuesWithToken}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="token"]').should('contain', 'Test Token');
  });

  it('handle multiple hosts by index', () => {
    const multiHostFormValues = {
      ...defaultFormValues,
      configuration: {
        ...defaultFormValues.configuration,
        hosts: [
          {
            id: 0,
            name: 'host1',
            address: '192.168.1.1',
            port: 4317,
            pollerCaCertificate: '',
            pollerCaName: '',
            token: null
          },
          {
            id: 1,
            name: 'host2',
            address: '192.168.1.2',
            port: 8080,
            pollerCaCertificate: '',
            pollerCaName: '',
            token: null
          }
        ]
      }
    };

    cy.mount({
      Component: (
        <TestWrapper initialValues={multiHostFormValues}>
          <TestComponent index={1} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="has-errors"]').should('contain', 'false');
    cy.get('[data-testid="has-touched"]').should('contain', 'false');
  });

  it('handle different connection modes correctly', () => {
    const modes = [
      { mode: ConnectionMode.secure, expected: 'true' },
      { mode: ConnectionMode.insecure, expected: 'true' },
      { mode: ConnectionMode.noTLS, expected: 'false' }
    ];

    modes.forEach(({ mode, expected }) => {
      const formValues = {
        ...defaultFormValues,
        connectionMode: { id: mode, name: mode }
      };

      cy.mount({
        Component: (
          <TestWrapper initialValues={formValues}>
            <TestComponent index={0} />
          </TestWrapper>
        )
      });

      cy.get('[data-testid="certificate-fields-visible"]').should(
        'contain',
        expected
      );
    });
  });

  it('clear form errors when host is selected', () => {
    const formWithErrors = {
      ...defaultFormValues,
      configuration: {
        ...defaultFormValues.configuration,
        hosts: [
          {
            ...defaultFormValues.configuration.hosts[0],
            address: '',
            port: 0
          }
        ]
      }
    };

    const errors = {
      configuration: {
        hosts: [
          {
            address: 'Address is required',
            port: 'Port is required'
          }
        ]
      }
    };

    cy.mount({
      Component: (
        <TestWrapper initialValues={formWithErrors} errors={errors}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="has-errors"]').should('contain', 'true');

    cy.get('[data-testid="select-host-button"]').click();
  });

  it('handle empty token array', () => {
    const formWithEmptyToken = {
      ...defaultFormValues,
      configuration: {
        ...defaultFormValues.configuration,
        hosts: [
          {
            ...defaultFormValues.configuration.hosts[0],
            token: []
          }
        ]
      }
    };

    cy.mount({
      Component: (
        <TestWrapper initialValues={formWithEmptyToken}>
          <TestComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="token"]').should('contain', '[]');
  });

  it('handle string input changes for different properties', () => {
    const TestStringInputComponent = ({ index }: { index: number }) => {
      const { changeStringInput } = useHostConfiguration({ index });

      return (
        <div>
          <input
            data-testid="ca-certificate-input"
            onChange={changeStringInput('pollerCaCertificate')}
            placeholder="CA Certificate"
          />
          <input
            data-testid="ca-name-input"
            onChange={changeStringInput('pollerCaName')}
            placeholder="CA Name"
          />
        </div>
      );
    };

    cy.mount({
      Component: (
        <TestWrapper initialValues={defaultFormValues}>
          <TestStringInputComponent index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="ca-certificate-input"]').type('cert-content');
    cy.get('[data-testid="ca-certificate-input"]').should(
      'have.value',
      'cert-content'
    );

    cy.get('[data-testid="ca-name-input"]').type('ca-name');
    cy.get('[data-testid="ca-name-input"]').should('have.value', 'ca-name');
  });
});
