import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Formik } from 'formik';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import ConnectionInitiated from './ConnectionInitiated';

interface Props {
  agentInitiated?: boolean;
  connectionMode?: string;
  /** Also adds an empty host, as an enabled poller configuration has one. */
  pollerInitiated?: boolean;
}

export const Default = ({
  agentInitiated = false,
  connectionMode = 'secure',
  pollerInitiated = false
}: Props): JSX.Element => {
  const queryClient = useMemo(() => new QueryClient(), []);
  const initialValues = {
    configuration: {
      agentInitiated,
      hosts: pollerInitiated
        ? [
            {
              address: '',
              pollerCaCertificate: '',
              pollerCaName: '',
              port: '',
              token: null
            }
          ]
        : [],
      otelCaCertificate: '',
      otelPrivateKey: '',
      otelPublicCertificate: '',
      pollerInitiated,
      tokens: []
    },
    connectionMode: { id: connectionMode }
  };

  return (
    <MemoryRouter>
      <QueryClientProvider client={queryClient}>
        <Formik initialValues={initialValues} onSubmit={() => undefined}>
          <ConnectionInitiated />
        </Formik>
      </QueryClientProvider>
    </MemoryRouter>
  );
};
