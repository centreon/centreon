import { renderHook, waitFor, RenderHookResult } from '@testing-library/react';
import fetchMock from 'jest-fetch-mock';

import TestQueryProvider from '../TestQueryProvider';

import useMutationQuery, {
  Method,
  UseMutationQueryProps,
  UseMutationQueryState
} from '.';

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

const renderMutationQuery = <T extends object>(
  params: UseMutationQueryProps<T>
): RenderHookResult<UseMutationQueryState<T>, unknown> =>
  renderHook(() => useMutationQuery<T>(params), {
    wrapper: TestQueryProvider
  }) as RenderHookResult<UseMutationQueryState<T>, unknown>;

describe('useFetchQuery', () => {
  beforeEach(() => {
    mockedShowErrorMessage.mockReset();
    fetchMock.resetMocks();
  });

  it('posts data to an endpoint', async () => {
    fetchMock.once(JSON.stringify({}));
    const { result } = renderMutationQuery<User>({
      getEndpoint: () => '/endpoint',
      method: Method.POST
    });

    result.current.mutate(user);

    await waitFor(() => {
      expect(result.current?.isError).toEqual(false);
    });
  });

  it("shows an error from the API via the Snackbar and inside the browser's console when posting data to an endpoint", async () => {
    fetchMock.once(JSON.stringify({ code: 2, message: 'custom message' }), {
      status: 400
    });
    const { result } = renderMutationQuery<User>({
      getEndpoint: () => '/endpoint',
      method: Method.POST
    });

    result.current.mutate(user);

    await waitFor(() => {
      expect(result.current?.isError).toEqual(true);
    });

    await waitFor(() => {
      expect(mockedShowErrorMessage).toHaveBeenCalledWith('custom message');
    });
  });

  it('shows a default failure message via the Snackbar as fallback when posting data to an API', async () => {
    fetchMock.once(JSON.stringify({}), {
      status: 400
    });

    const { result } = renderMutationQuery<User>({
      getEndpoint: () => '/endpoint',
      method: Method.POST
    });

    result.current.mutate(user);

    await waitFor(() => {
      expect(result.current?.isError).toEqual(true);
    });

    await waitFor(() => {
      expect(mockedShowErrorMessage).toHaveBeenCalledWith(
        'Something went wrong'
      );
    });
  });

  it('does not show any message via the Snackbar when the httpCodesBypassErrorSnackbar is passed when posting data to an API', async () => {
    fetchMock.once(JSON.stringify({}), {
      status: 400
    });

    const { result } = renderMutationQuery<User>({
      getEndpoint: () => '/endpoint',
      httpCodesBypassErrorSnackbar: [400],
      method: Method.POST
    });

    result.current.mutate(user);

    await waitFor(() => {
      expect(mockedShowErrorMessage).not.toHaveBeenCalled();
    });
  });
});
