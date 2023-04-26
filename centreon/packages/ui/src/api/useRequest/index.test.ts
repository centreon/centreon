import axios from 'axios';
import anyLogger from 'anylogger';
import { RenderHookResult, renderHook, act } from '@testing-library/react';

import useRequest, { RequestResult, RequestParams } from '.';

const mockedAxios = axios as jest.Mocked<typeof axios>;

const mockedShowErrorMessage = jest.fn();

jest.mock('../../Snackbar/useSnackbar', () => ({
  __esModule: true,
  default: jest
    .fn()
    .mockImplementation(() => ({ showErrorMessage: mockedShowErrorMessage }))
}));

interface Result {
  data: string;
}

const request = jest.fn();

const renderUseRequest = (
  requestParams: RequestParams<Result>
): RenderHookResult<unknown, RequestResult<Result>> =>
  renderHook(() => useRequest(requestParams));

describe(useRequest, () => {
  afterEach(() => {
    request.mockReset();
    mockedShowErrorMessage.mockReset();
    mockedAxios.isCancel.mockReset();
  });

  it('resolves with the result of the given request', async () => {
    request.mockImplementation(() => jest.fn().mockResolvedValue('success'));

    const { result } = renderUseRequest({
      request
    });

    await act(async () =>
      result.current.sendRequest().then((data) => {
        expect(data).toEqual('success');
      })
    );
  });

  it('shows an error via the Snackbar using the result of the given getErrorMessage function when it is passed', async () => {
    request.mockImplementation(() => jest.fn().mockRejectedValue({}));

    const getErrorMessage = (): string => 'custom message';

    const { result } = renderUseRequest({ getErrorMessage, request });

    await act(async () => {
      result.current.sendRequest().catch((error) => {
        expect(error).toEqual({});
      });
    });

    expect(mockedShowErrorMessage).toHaveBeenCalledWith('custom message');
  });

  it("shows an error via the Snackbar and inside browser's console using the error message from the API when available", async () => {
    const response = {
      response: { data: { message: 'failure' } }
    };
    request.mockImplementation(() => jest.fn().mockRejectedValue(response));

    const { result } = renderUseRequest({ request });

    await act(async () => {
      result.current.sendRequest().catch((error) => {
        expect(error).toEqual(response);
      });
    });

    expect(anyLogger().error).toHaveBeenCalledWith(response);

    expect(mockedShowErrorMessage).toHaveBeenCalledWith('failure');
  });

  it('shows a default failure message via the Snackbar as fallback', async () => {
    request.mockImplementation(() => jest.fn().mockRejectedValue({}));

    const { result } = renderUseRequest({
      defaultFailureMessage: 'Oops',
      request
    });

    await act(async () => {
      result.current.sendRequest().catch((error) => {
        expect(error).toEqual({});
      });
    });

    expect(mockedShowErrorMessage).toHaveBeenCalledWith('Oops');
  });

  it('does not show any message via the Snackbar when the error is an axios cancel', async () => {
    mockedAxios.isCancel.mockReturnValue(true);

    request.mockImplementation(() => jest.fn().mockRejectedValue({}));

    const { result } = renderUseRequest({
      request
    });

    await act(async () => {
      result.current.sendRequest().catch((error) => {
        expect(error).toEqual({});
      });
    });

    expect(mockedShowErrorMessage).not.toHaveBeenCalled();
  });
});
