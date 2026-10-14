import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Formik, useFormikContext } from 'formik';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import AgentInitiated from './AgentInitiated';

interface Props {
  configuration?: Record<string, unknown>;
  /** Marks every field as touched and in error. */
  withErrors?: boolean;
}

const defaultConfiguration = {
  otelCaCertificate: '',
  otelPrivateKey: '',
  otelPublicCertificate: '',
  tokens: [
    { id: 1, inputValue: 'testInput', name: 'testName' },
    { id: 2, inputValue: 'testInput2', name: 'testName2', testId: 'testId2' }
  ]
};

const FormValues = (): JSX.Element => {
  const { values } = useFormikContext();

  return (
    <form hidden>
      <input
        data-testid="form-values"
        readOnly
        value={JSON.stringify(values)}
      />
    </form>
  );
};

export const Default = ({
  configuration = defaultConfiguration,
  withErrors = false
}: Props): JSX.Element => {
  const queryClient = useMemo(() => new QueryClient(), []);

  return (
    <MemoryRouter>
      <QueryClientProvider client={queryClient}>
        <Formik
          initialErrors={
            withErrors
              ? {
                  configuration: {
                    otelCaCertificate: 'CA certificate is required',
                    otelPrivateKey: 'Private key is required',
                    otelPublicCertificate: 'Public certificate is required',
                    tokens: 'At least one token is required'
                  }
                }
              : {}
          }
          initialTouched={
            withErrors
              ? {
                  configuration: {
                    otelCaCertificate: true,
                    otelPrivateKey: true,
                    otelPublicCertificate: true,
                    tokens: true
                  }
                }
              : {}
          }
          initialValues={{
            configuration,
            connectionMode: { id: 'secure', name: 'TLS' }
          }}
          onSubmit={() => undefined}
        >
          <>
            <AgentInitiated />
            <FormValues />
          </>
        </Formik>
      </QueryClientProvider>
    </MemoryRouter>
  );
};
