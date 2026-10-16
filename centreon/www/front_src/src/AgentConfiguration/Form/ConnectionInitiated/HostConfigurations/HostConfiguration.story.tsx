import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Formik } from 'formik';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import { type AgentConfigurationForm, ConnectionMode } from '../../../models';
import HostConfiguration from './HostConfiguration';

const host = {
  address: '192.168.1.1',
  id: 1,
  name: 'test-host',
  pollerCaCertificate: '',
  pollerCaName: '',
  port: 8080,
  token: null
};

interface Props {
  connectionMode?: { id: string; name: string };
}

export const Default = ({
  connectionMode = { id: ConnectionMode.secure, name: 'Secure' }
}: Props): JSX.Element => {
  const queryClient = useMemo(
    () =>
      new QueryClient({
        defaultOptions: {
          mutations: { retry: false },
          queries: { retry: false }
        }
      }),
    []
  );
  const initialValues = {
    configuration: {
      agentInitiated: true,
      hosts: [host],
      otelCaCertificate: '',
      otelPrivateKey: '',
      otelPublicCertificate: '',
      pollerInitiated: false
    },
    connectionMode,
    name: '',
    pollers: [],
    type: null
  } as unknown as AgentConfigurationForm;

  return (
    <MemoryRouter>
      <QueryClientProvider client={queryClient}>
        <Formik initialValues={initialValues} onSubmit={() => undefined}>
          <HostConfiguration host={host as never} index={0} />
        </Formik>
      </QueryClientProvider>
    </MemoryRouter>
  );
};
