import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import fetchMock from 'jest-fetch-mock';
import { createElement, type ReactNode } from 'react';

import useFetchQuery from '../useFetchQuery';
import usePrefetchQuery from '.';

jest.mock('../../Snackbar/useSnackbar', () => ({
  __esModule: true,
  default: jest.fn().mockImplementation(() => ({ showErrorMessage: jest.fn() }))
}));

interface User {
  email: string;
  name: string;
}

const user: User = {
  email: 'john@doe.com',
  name: 'John Doe'
};

const mockParams = { headers: { 'Content-Type': 'application/json' } };

const queryKey = ['prefetchedUser'];

const prefetchProps = {
  getEndpoint: (): string => '/endpoint',
  getQueryKey: (): Array<string> => queryKey
};

const createWrapper = (): (({
  children
}: {
  children: ReactNode;
}) => JSX.Element) => {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 60_000, retry: false } }
  });

  return ({ children }) =>
    createElement(QueryClientProvider, { client: queryClient }, children);
};

describe('usePrefetchQuery', () => {
  beforeEach(() => {
    fetchMock.resetMocks();
  });

  it('caches a successful response for a later useFetchQuery call with the same key', async () => {
    fetchMock.once(JSON.stringify(user), mockParams);
    const wrapper = createWrapper();

    renderHook(() => usePrefetchQuery<User>(prefetchProps), { wrapper });

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    const { result } = renderHook(
      () =>
        useFetchQuery<User>({
          ...prefetchProps,
          queryOptions: { refetchOnMount: false }
        }),
      { wrapper }
    );

    await waitFor(() => {
      expect(result.current.data).toEqual(user);
    });
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('does not cache an error response, so the later useFetchQuery call fetches again', async () => {
    fetchMock.once(JSON.stringify({ code: 2, message: 'custom message' }), {
      ...mockParams,
      status: 500
    });
    const wrapper = createWrapper();

    renderHook(() => usePrefetchQuery<User>(prefetchProps), { wrapper });

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    fetchMock.once(JSON.stringify(user), mockParams);

    const { result } = renderHook(
      () =>
        useFetchQuery<User>({
          ...prefetchProps,
          queryOptions: { refetchOnMount: false }
        }),
      { wrapper }
    );

    await waitFor(() => {
      expect(result.current.data).toEqual(user);
    });
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });
});
