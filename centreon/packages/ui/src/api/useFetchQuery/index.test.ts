import { renderHook, waitFor, RenderHookResult } from '@testing-library/react';
import fetchMock from 'jest-fetch-mock';
import anyLogger from 'anylogger';

import TestQueryProvider from '../TestQueryProvider';

import useFetchQuery, { UseFetchQueryProps, UseFetchQueryState } from '.';

const mockedShowErrorMessage = jest.fn();

interface User {
  email: string;
  name: string;
}

const user: User = {
  email: 'john@doe.com',
  name: 'John Doe'
};

jest.mock('../../Snackbar/useSnackbar', () => ({
  __esModule: true,
  default: jest
    .fn()
    .mockImplementation(() => ({ showErrorMessage: mockedShowErrorMessage }))
}));

const renderFetchQuery = <T extends object>(
  params: UseFetchQueryProps<T>
): RenderHookResult<UseFetchQueryState<T>, unknown> =>
  renderHook(() => useFetchQuery<T>(params), {
    wrapper: TestQueryProvider
  }) as RenderHookResult<UseFetchQueryState<T>, unknown>;

describe('useFetchQuery', () => {
  beforeEach(() => {
    mockedShowErrorMessage.mockReset();
    fetchMock.resetMocks();
  });

  it('does not show any message via the Snackbar when the error is a cancellation', async () => {
    fetchMock.mockAbortOnce();

    renderFetchQuery<User>({
      getEndpoint: () => '/endpoint',
      getQueryKey: () => ['queryKey']
    });

    await waitFor(() => {
      expect(mockedShowErrorMessage).not.toHaveBeenCalled();
    });
  });

  it('retrieves data from an endpoint', async () => {
    fetchMock.once(JSON.stringify(user));
    const { result } = renderFetchQuery<User>({
      getEndpoint: () => '/endpoint',
      getQueryKey: () => ['queryKey']
    });

    await waitFor(() => {
      expect(result.current?.data).toEqual(user);
    });
  });

  it("shows an error from the API via the Snackbar and inside the browser's console", async () => {
    fetchMock.once(JSON.stringify({ code: 2, message: 'custom message' }), {
      status: 400
    });

    const { result } = renderFetchQuery<User>({
      getEndpoint: () => '/endpoint',
      getQueryKey: () => ['queryKey']
    });

    await waitFor(() => {
      expect(mockedShowErrorMessage).toHaveBeenCalledWith('custom message');
    });

    expect(anyLogger().error).toHaveBeenCalledWith('custom message');

    await waitFor(() => {
      expect(result.current.error).toStrictEqual({
        message: 'custom message',
        statusCode: 400
      });
    });
  });

  it("shows an error from the API via the Snackbar and inside the browser's console when the API does not respond", async () => {
    fetchMock.mockReject(new TypeError('error'));

    renderFetchQuery<User>({
      getEndpoint: () => '/endpoint',
      getQueryKey: () => ['queryKey']
    });

    await waitFor(() => {
      expect(mockedShowErrorMessage).toHaveBeenCalledWith('error');
    });

    expect(anyLogger().error).toHaveBeenCalledWith('error');
  });

  it('shows a default failure message via the Snackbar as fallback', async () => {
    fetchMock.once(JSON.stringify({}), {
      status: 400
    });

    const { result } = renderFetchQuery<User>({
      getEndpoint: () => '/endpoint',
      getQueryKey: () => ['queryKey']
    });

    await waitFor(() => {
      expect(mockedShowErrorMessage).toHaveBeenCalledWith(
        'Something went wrong'
      );
    });

    await waitFor(() => {
      expect(result.current.error).toStrictEqual({
        message: 'Something went wrong',
        statusCode: 400
      });
    });
  });

  it('does not show any message via the Snackbar when the httpCodesBypassErrorSnackbar is passed', async () => {
    fetchMock.once(JSON.stringify({}), {
      status: 400
    });

    const { result } = renderFetchQuery<User>({
      getEndpoint: () => '/endpoint',
      getQueryKey: () => ['queryKey'],
      httpCodesBypassErrorSnackbar: [400]
    });

    await waitFor(() => {
      expect(mockedShowErrorMessage).not.toHaveBeenCalled();
    });

    await waitFor(() => {
      expect(result.current.error).toStrictEqual({
        message: 'Something went wrong',
        statusCode: 400
      });
    });
  });
});
