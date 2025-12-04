import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Formik } from 'formik';
import { BrowserRouter } from 'react-router';
import { AgentConfigurationForm, ConnectionMode } from '../../../models';
import HostConfiguration from './HostConfiguration';

const mockHost = {
  id: 1,
  name: 'test-host',
  address: '192.168.1.1',
  port: 8080,
  pollerCaCertificate: '',
  pollerCaName: '',
  token: null
};

const mockFormValues: AgentConfigurationForm = {
  connectionMode: { id: ConnectionMode.secure, name: 'Secure' },
  configuration: {
    hosts: [mockHost],
    agentInitiated: true,
    pollerInitiated: false,
    otelPublicCertificate: '',
    otelCaCertificate: '',
    otelPrivateKey: ''
  },
  type: null,
  name: '',
  pollers: []
};

const TestWrapper = ({ children, initialValues = mockFormValues }) => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        refetchOnWindowFocus: false,
        staleTime: 0,
        gcTime: 0
      },
      mutations: {
        retry: false
      }
    }
  });

  return (
    <BrowserRouter>
      <QueryClientProvider client={queryClient}>
        <Formik
          initialValues={initialValues}
          onSubmit={() => {}}
          validate={() => ({})}
        >
          {children}
        </Formik>
      </QueryClientProvider>
    </BrowserRouter>
  );
};

describe('HostConfiguration', () => {
  beforeEach(() => {
    cy.mount({
      Component: (
        <TestWrapper>
          <HostConfiguration index={0} host={mockHost} />
        </TestWrapper>
      )
    });
  });

  it('renders all required fields', () => {
    cy.get('[id="DNSIP-label"]').should('be.visible');
    cy.get('[id="Port-label"]').should('be.visible');
  });

  it('displays the correct host values', () => {
    cy.findByDisplayValue('192.168.1.1').should('be.visible');
    cy.findByDisplayValue('8080').should('be.visible');
  });

  it('shows certificate fields when connection mode is secure', () => {
    cy.get('[data-testid="CA(.crt,.cer)"]').should('be.visible');
    cy.get('[data-testid="CA Common Name (CN)"]').should('be.visible');
    cy.get('[data-testid="Select existing CMA token"]').should('be.visible');
  });

  it('displays the host name correctly', () => {
    cy.findByDisplayValue('test-host').should('be.visible');
  });

  it('hides certificate fields when connection mode is not secure or insecure', () => {
    const insecureFormValues = {
      ...mockFormValues,
      connectionMode: { id: 'other', name: 'Other' }
    };

    cy.mount({
      Component: (
        <TestWrapper initialValues={insecureFormValues}>
          <HostConfiguration index={0} host={mockHost} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="CA(.crt,.cer)"]').should('not.exist');
    cy.get('[data-testid="CA Common Name (CN)"]').should('not.exist');
    cy.get('[data-testid="Select existing CMA token"]').should('be.visible');
  });
});
