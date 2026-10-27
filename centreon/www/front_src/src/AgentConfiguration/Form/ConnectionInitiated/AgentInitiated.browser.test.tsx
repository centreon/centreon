import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { getRequestCalls, interceptAPIRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Formik, type FormikErrors, type FormikTouched } from 'formik';
import { BrowserRouter } from 'react-router';

import { ConnectionMode } from '../../models';
import {
  labelCaCertificate,
  labelCMAauthenticationToken,
  labelCreateHostAutomatically,
  labelCreateNewCMAToken,
  labelOTLPReceiver,
  labelPort,
  labelPrivateKey,
  labelPublicCertificate,
  labelSelectExistingCMATokens
} from '../../translatedLabels';
import AgentInitiated from './AgentInitiated';

interface FormValues {
  configuration: {
    otelCaCertificate: string | null;
    otelPrivateKey: string | null;
    otelPublicCertificate: string | null;
    tokens?: Array<{
      id: number;
      inputValue: string;
      name: string;
      testId?: string;
    }> | null;
  };
  connectionMode: { id: string; name: string };
}

const initialValues: FormValues = {
  configuration: {
    otelCaCertificate: '',
    otelPrivateKey: '',
    otelPublicCertificate: '',
    tokens: [
      { id: 1, inputValue: 'testInput', name: 'testName' },
      { id: 2, inputValue: 'testInput2', name: 'testName2', testId: 'testId2' }
    ]
  },
  connectionMode: {
    id: ConnectionMode.secure,
    name: 'TLS'
  }
};

const valuesWithErrors: FormValues = {
  configuration: {
    otelCaCertificate: '',
    otelPrivateKey: '',
    otelPublicCertificate: '',
    tokens: []
  },
  connectionMode: {
    id: ConnectionMode.secure,
    name: 'TLS'
  }
};

const mockErrors = {
  configuration: {
    otelCaCertificate: 'CA certificate is required',
    otelPrivateKey: 'Private key is required',
    otelPublicCertificate: 'Public certificate is required',
    tokens: 'At least one token is required'
  },
  connectionMode: {
    id: ConnectionMode.secure,
    name: 'TLS'
  }
};

const mockTouched = {
  configuration: {
    otelCaCertificate: true,
    otelPrivateKey: true,
    otelPublicCertificate: true,
    tokens: true
  }
};

const tokensListing = {
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 1 },
  result: [{ creator: { id: 1, name: 'admin' }, name: 'new-token' }]
};

const initialize = (
  values: FormValues = initialValues,
  errors: FormikErrors<FormValues> = {},
  touched: FormikTouched<FormValues> = {}
): void => {
  interceptAPIRequest({
    alias: 'getTokens',
    method: Method.GET,
    path: '**/administration/tokens**',
    response: tokensListing
  });

  render(
    <BrowserRouter>
      <QueryClientProvider client={new QueryClient()}>
        <Formik
          initialErrors={errors}
          initialTouched={touched}
          initialValues={values}
          onSubmit={(): void => undefined}
        >
          <AgentInitiated />
        </Formik>
      </QueryClientProvider>
    </BrowserRouter>
  );
};

// `@centreon/ui` text fields put the test id on the wrapper and on the input.
const getField = (label: string): HTMLElement =>
  screen.getAllByTestId(label)[0];

const getInput = (label: string): HTMLInputElement =>
  getField(label).querySelector('input') as HTMLInputElement;

const getTokensInput = (): HTMLElement => screen.getByRole('combobox');

describe('AgentInitiated', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('should render the component with initial values', () => {
    initialize();
    expect(getInput(labelPublicCertificate)).toHaveValue('');
    expect(getInput(labelCaCertificate)).toHaveValue('');
    expect(getInput(labelPrivateKey)).toHaveValue('');
    expect(getField(labelSelectExistingCMATokens)).toBeVisible();

    // The automatic host creation is off, the OTLP receiver section has an
    // empty port, and the selected tokens are shown as chips.
    expect(
      screen.getByRole('checkbox', { name: labelCreateHostAutomatically })
    ).not.toBeChecked();
    expect(screen.getByText(labelOTLPReceiver)).toBeVisible();
    expect(getField(labelPort)).toBeVisible();
    expect(screen.getByTestId('portInput')).toHaveValue(null);
    expect(screen.getByText(labelCMAauthenticationToken)).toBeVisible();
    expect(screen.getByTestId('tag-option-chip-1')).toHaveTextContent(
      'testName'
    );
    expect(screen.getByTestId('tag-option-chip-2')).toHaveTextContent(
      'testName2'
    );
    expect(screen.getByText(labelCreateNewCMAToken)).toBeVisible();
  });

  it('should display error messages when fields are touched and have errors', () => {
    initialize(valuesWithErrors, mockErrors, mockTouched);

    expect(getField(labelPublicCertificate)).toBeInTheDocument();
    expect(getField(labelCaCertificate)).toBeInTheDocument();
    expect(getField(labelPrivateKey)).toBeInTheDocument();
    expect(getField(labelSelectExistingCMATokens)).toBeInTheDocument();

    expect(screen.getByText('Public certificate is required')).toBeVisible();
    expect(screen.getByText('CA certificate is required')).toBeVisible();
    expect(screen.getByText('Private key is required')).toBeVisible();
    expect(screen.getByText('At least one token is required')).toBeVisible();
  });

  it('should render with pre-filled certificate values', () => {
    const prefilledValues = {
      configuration: {
        otelCaCertificate: 'existing-ca.cert',
        otelPrivateKey: 'existing-private.key',
        otelPublicCertificate: 'existing-public.crt',
        tokens: []
      },
      connectionMode: {
        id: ConnectionMode.secure,
        name: 'TLS'
      }
    };

    initialize(prefilledValues);

    expect(getInput(labelPublicCertificate)).toHaveValue('existing-public.crt');
    expect(getInput(labelCaCertificate)).toHaveValue('existing-ca.cert');
    expect(getInput(labelPrivateKey)).toHaveValue('existing-private.key');
  });

  it('should handle empty tokens array', () => {
    const emptyTokensValues = {
      configuration: {
        otelCaCertificate: '',
        otelPrivateKey: '',
        otelPublicCertificate: '',
        tokens: []
      },
      connectionMode: { id: 'secure', name: 'secure' }
    };

    initialize(emptyTokensValues);
    expect(getField(labelSelectExistingCMATokens)).toBeVisible();
    expect(screen.queryAllByTestId(/^tag-option-chip-/)).toHaveLength(0);
  });

  it('should render tokens field as required', () => {
    initialize();
    expect(
      getField(labelSelectExistingCMATokens).parentElement
    ).toHaveTextContent('*');
  });

  it('should have correct aria-labels for accessibility', () => {
    initialize();

    expect(getInput(labelPublicCertificate)).toHaveAttribute(
      'aria-label',
      'Public certificate (.crt, .cert, .cer)'
    );
    expect(getInput(labelCaCertificate)).toHaveAttribute(
      'aria-label',
      'CA (.crt, .cert, .cer)'
    );
    expect(getInput(labelPrivateKey)).toHaveAttribute(
      'aria-label',
      'Private key (.key)'
    );
  });

  it('should handle null values for certificates', () => {
    const nullValues = {
      configuration: {
        otelCaCertificate: null,
        otelPrivateKey: null,
        otelPublicCertificate: null,
        tokens: null
      },
      connectionMode: {
        id: ConnectionMode.secure,
        name: 'TLS'
      }
    };

    initialize(nullValues);

    expect(getInput(labelPublicCertificate)).toHaveValue('');
    expect(getInput(labelCaCertificate)).toHaveValue('');
    expect(getInput(labelPrivateKey)).toHaveValue('');
  });

  it('should update the public certificate field', async () => {
    initialize();
    await userEvent.clear(getInput(labelPublicCertificate));
    await userEvent.type(getInput(labelPublicCertificate), 'test.cert');
    expect(getInput(labelPublicCertificate)).toHaveValue('test.cert');
  });

  it('should update the CA certificate field', async () => {
    initialize();
    await userEvent.clear(getInput(labelCaCertificate));
    await userEvent.type(getInput(labelCaCertificate), 'test_ca.cert');
    expect(getInput(labelCaCertificate)).toHaveValue('test_ca.cert');
  });

  it('should update the private key field', async () => {
    initialize();
    await userEvent.clear(getInput(labelPrivateKey));
    await userEvent.type(getInput(labelPrivateKey), 'test.key');
    expect(getInput(labelPrivateKey)).toHaveValue('test.key');
  });

  it('should clear certificate fields when empty string is entered', async () => {
    const prefilledValues = {
      configuration: {
        otelCaCertificate: 'existing-ca.crt',
        otelPrivateKey: 'existing-private.key',
        otelPublicCertificate: 'existing-public.crt'
      },
      connectionMode: {
        id: ConnectionMode.secure,
        name: 'TLS'
      }
    };

    initialize(prefilledValues);

    await userEvent.clear(getInput(labelPublicCertificate));
    expect(getInput(labelPublicCertificate)).toHaveValue('');
  });

  it('should handle CMA tokens selection and change', async () => {
    initialize();

    await userEvent.click(getTokensInput());
    await userEvent.type(getTokensInput(), 'new-token');

    await userEvent.click(
      await screen.findByRole('option', { name: 'new-token' })
    );

    // The picked token is added next to the ones already selected.
    expect(
      await screen.findByTestId('tag-option-chip-new-token_1')
    ).toHaveTextContent('new-token');
    expect(screen.getByTestId('tag-option-chip-1')).toHaveTextContent(
      'testName'
    );
    expect(screen.getByTestId('tag-option-chip-2')).toHaveTextContent(
      'testName2'
    );
  });

  it('should handle token deletion', async () => {
    const valuesWithTokens = {
      configuration: {
        otelCaCertificate: '',
        otelPrivateKey: '',
        otelPublicCertificate: '',
        tokens: [
          { id: 1, inputValue: 'input1', name: 'token1' },
          { id: 2, inputValue: 'input2', name: 'token2' }
        ]
      },
      connectionMode: { id: 'secure', name: 'secure' }
    };

    initialize(valuesWithTokens);

    expect(getField(labelSelectExistingCMATokens)).toBeVisible();

    await userEvent.click(screen.getAllByTestId('CancelIcon')[0]);

    expect(screen.getByTestId('tag-option-chip-2')).toHaveTextContent('token2');
    await waitFor(() => {
      expect(screen.queryByTestId('tag-option-chip-1')).not.toBeInTheDocument();
    });
  });

  it('should handle empty tokens when changing CMA tokens', async () => {
    initialize();

    await userEvent.click(getTokensInput());
    expect(
      await screen.findByRole('option', { name: 'new-token' })
    ).toBeVisible();

    await userEvent.click(document.body);

    // Closing the list without picking anything keeps the selection.
    await waitFor(() => {
      expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });
    expect(screen.getAllByTestId(/^tag-option-chip-/)).toHaveLength(2);
  });

  it('should call changeCMATokens when token selection changes', async () => {
    initialize();

    await userEvent.click(getTokensInput());

    await userEvent.type(getTokensInput(), 'test-token');
    expect(getTokensInput()).toHaveValue('test-token');

    // The typed text searches the tokens.
    await waitFor(() => {
      expect(
        decodeURIComponent(
          getRequestCalls('@getTokens').at(-1)?.request.url.search ?? ''
        )
      ).toContain('test-token');
    });

    await userEvent.clear(getTokensInput());
    expect(getTokensInput()).toHaveValue('');
    expect(screen.getAllByTestId(/^tag-option-chip-/)).toHaveLength(2);
  });

  it('should properly remove tokens when delete is clicked', async () => {
    const valuesWithMultipleTokens = {
      configuration: {
        otelCaCertificate: '',
        otelPrivateKey: '',
        otelPublicCertificate: '',
        tokens: [
          { id: 1, inputValue: 'input1', name: 'token1' },
          { id: 2, inputValue: 'input2', name: 'token2' },
          { id: 3, inputValue: 'input3', name: 'token3' }
        ]
      },
      connectionMode: { id: 'secure', name: 'secure' }
    };

    initialize(valuesWithMultipleTokens);

    expect(screen.getByTestId('tag-option-chip-1')).toHaveTextContent('token1');
    expect(screen.getByTestId('tag-option-chip-2')).toHaveTextContent('token2');
    expect(screen.getByTestId('tag-option-chip-3')).toHaveTextContent('token3');

    await userEvent.click(screen.getAllByTestId('CancelIcon')[0]);

    expect(screen.getByTestId('tag-option-chip-2')).toHaveTextContent('token2');
    expect(screen.getByTestId('tag-option-chip-3')).toHaveTextContent('token3');

    await userEvent.click(screen.getAllByTestId('CancelIcon')[0]);

    // Each click removes the first remaining token.
    await waitFor(() => {
      expect(screen.getAllByTestId(/^tag-option-chip-/)).toHaveLength(1);
    });
    expect(screen.getByTestId('tag-option-chip-3')).toHaveTextContent('token3');
  });
});
