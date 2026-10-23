import { rs } from '@rstest/core';
import { interceptAPIRequest, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import SnackbarProvider from '../../Snackbar/SnackbarProvider';
import TestQueryProvider from '../TestQueryProvider';
import useMutationQuery, { Method, type UseMutationQueryProps } from '.';

interface Payload {
  a: string;
  b: number;
  c: Array<string>;
}

type MutationProps = Omit<
  UseMutationQueryProps<Payload, unknown>,
  'getEndpoint'
>;

const TestComponent = (props: MutationProps): JSX.Element => {
  const mutation = useMutationQuery<Payload, unknown>({
    ...props,
    getEndpoint: () => '/endpoint'
  });

  return (
    <button
      onClick={() =>
        mutation.mutateAsync({ payload: { a: 'a', b: 2, c: ['arr', 'ay'] } })
      }
      type="button"
    >
      Send
    </button>
  );
};

interface InitializeProps {
  isError?: boolean;
  mutationProps: MutationProps & {
    getEndpoint: () => string;
  };
}

const initialize = ({
  mutationProps,
  isError = false
}: InitializeProps): void => {
  interceptAPIRequest({
    alias: 'mutateEndpoint',
    method: mutationProps.method,
    path: './api/latest/endpoint',
    response: isError
      ? {
          message: 'custom error message'
        }
      : undefined,
    statusCode: isError ? 400 : 204
  });

  render(
    <SnackbarProvider>
      <TestQueryProvider>
        <TestComponent {...mutationProps} />
      </TestQueryProvider>
    </SnackbarProvider>
  );
};

describe('useMutationQuery', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  afterEach(() => {
    rs.restoreAllMocks();
  });

  it('sends data to an endpoint', async () => {
    // The recorded request has no headers: they are read from the call to
    // `fetch` (the spy calls through).
    const fetch = rs.spyOn(window, 'fetch');

    initialize({
      mutationProps: {
        getEndpoint: () => '/endpoint',
        method: Method.POST
      }
    });

    await userEvent.click(screen.getByRole('button'));

    const { request } = await waitForRequest('@mutateEndpoint');

    expect(request.method).toBe('POST');
    expect(request.body).toEqual({ a: 'a', b: 2, c: ['arr', 'ay'] });
    expect(
      new Headers(fetch.mock.calls[0][1]?.headers).get('content-type')
    ).toBe('application/json');
  });

  it("shows an error from the API via the Snackbar and inside the browser's console when posting data to an endpoint", async () => {
    const consoleError = rs
      .spyOn(console, 'error')
      .mockImplementation(() => undefined);

    initialize({
      isError: true,
      mutationProps: {
        getEndpoint: () => '/endpoint',
        method: Method.POST
      }
    });

    await userEvent.click(screen.getByRole('button'));

    expect(await screen.findByText('custom error message')).toBeVisible();

    expect(consoleError).toHaveBeenCalledWith(
      'API Request: custom error message'
    );
  });

  it('does not show any message via the Snackbar when the httpCodesBypassErrorSnackbar is passed when posting data to an API', async () => {
    const consoleError = rs
      .spyOn(console, 'error')
      .mockImplementation(() => undefined);

    initialize({
      isError: true,
      mutationProps: {
        getEndpoint: () => '/endpoint',
        httpCodesBypassErrorSnackbar: [400],
        method: Method.POST
      }
    });

    await userEvent.click(screen.getByRole('button'));

    // The error is handled (and logged) once the answer is applied. The
    // snackbar would show in a later render: it is given 500 ms to appear.
    await waitFor(() => {
      expect(consoleError).toHaveBeenCalledWith(
        'API Request: custom error message'
      );
    });

    await expect(
      screen.findByText('custom error message', {}, { timeout: 500 })
    ).rejects.toThrow();
  });
});
