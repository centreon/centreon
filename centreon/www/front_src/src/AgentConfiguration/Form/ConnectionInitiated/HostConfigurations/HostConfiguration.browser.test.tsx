import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@test-setup/render';
import { cleanup, screen } from '@testing-library/react';
import { Formik } from 'formik';
import type { ReactNode } from 'react';
import { BrowserRouter } from 'react-router';

import {
  type AgentConfigurationForm,
  ConnectionMode,
  type HostConfiguration as HostConfigurationModel
} from '../../../models';
import {
  labelCACommonName,
  labelCaCertificate,
  labelSelectExistingCMAToken
} from '../../../translatedLabels';
import HostConfiguration from './HostConfiguration';

const mockHost = {
  address: '192.168.1.1',
  id: 1,
  name: 'test-host',
  pollerCaCertificate: '',
  pollerCaName: '',
  port: 8080,
  token: null
} as unknown as HostConfigurationModel;

const mockFormValues = {
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
} as unknown as AgentConfigurationForm;

interface TestWrapperProps {
  children: ReactNode;
  initialValues?: AgentConfigurationForm;
}

const TestWrapper = ({
  children,
  initialValues = mockFormValues
}: TestWrapperProps): ReactNode => {
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
          onSubmit={(): void => undefined}
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
    expect.hasAssertions();

    render(
      <TestWrapper>
        <HostConfiguration host={mockHost} index={0} />
      </TestWrapper>
    );
  });

  it('renders all required fields', () => {
    expect(document.querySelector('[id="DNSIP-label"]')).toBeVisible();
    expect(document.querySelector('[id="Port-label"]')).toBeVisible();
  });

  it('displays the correct host values', () => {
    expect(screen.getByDisplayValue('192.168.1.1')).toBeVisible();
    expect(screen.getByDisplayValue('8080')).toBeVisible();
  });

  it('shows certificate fields when connection mode is secure', () => {
    expect(screen.getAllByTestId(labelCaCertificate)[0]).toBeVisible();
    expect(screen.getAllByTestId(labelSelectExistingCMAToken)[0]).toBeVisible();
  });

  it('displays the host name correctly', () => {
    expect(screen.getByDisplayValue('test-host')).toBeVisible();
  });

  it('hides certificate fields when connection mode is not secure or insecure', async () => {
    const insecureFormValues = {
      ...mockFormValues,
      connectionMode: { id: 'other', name: 'Other' }
    } as unknown as AgentConfigurationForm;

    // Replaces the form rendered for the other tests.
    cleanup();
    render(
      <TestWrapper initialValues={insecureFormValues}>
        <HostConfiguration host={mockHost} index={0} />
      </TestWrapper>
    );

    expect(
      (await screen.findAllByTestId(labelSelectExistingCMAToken))[0]
    ).toBeVisible();
    expect(screen.queryAllByTestId(labelCaCertificate)).toHaveLength(0);
    expect(screen.queryAllByTestId(labelCACommonName)).toHaveLength(0);
  });
});
