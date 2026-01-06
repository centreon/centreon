import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Formik } from 'formik';
import { BrowserRouter } from 'react-router';

import { AgentConfigurationForm, ConnectionMode } from '../../../models';
import HostConfiguration from './HostConfiguration';

const mockHost = {
  address: '192.168.1.1',
  id: 1,
  name: 'test-host',
  pollerCaCertificate: '',
  pollerCaName: '',
  port: 8080,
  token: null
};

const mockFormValues: AgentConfigurationForm = {
  configuration: {
    agentInitiated: true,
    hosts: [mockHost],
    otelCaCertificate: '',
    otelPrivateKey: '',
    otelPublicCertificate: '',
    pollerInitiated: false
  },
  connectionMode: { id: ConnectionMode.secure, name: 'Secure' },
  name: '',
  pollers: [],
  type: null
};

const TestWrapper = ({ children, initialValues = mockFormValues }) => {
  const queryClient = new QueryClient({
    defaultOptions: {
      mutations: {
        retry: false
      },
      queries: {
        gcTime: 0,
        refetchOnWindowFocus: false,
        retry: false,
        staleTime: 0
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
          <HostConfiguration host={mockHost} index={0} />
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
    cy.get('[data-testid="CA (.crt, .cert, .cer)"]').should('be.visible');
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
          <HostConfiguration host={mockHost} index={0} />
        </TestWrapper>
      )
    });

    cy.get('[data-testid="CA (.crt, .cert, .cer)"]').should('not.exist');
    cy.get('[data-testid="CA Common Name (CN)"]').should('not.exist');
    cy.get('[data-testid="Select existing CMA token"]').should('be.visible');
  });
});
