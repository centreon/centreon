import {
  renderHook,
  act,
  RenderHookResult,
} from '@testing-library/react-hooks';

import axios from 'axios';

import useRequest, { RequestResult, RequestParams } from '.';
import { Severity } from '../..';

const mockedAxios = axios as jest.Mocked<typeof axios>;

const mockedShowMessage = jest.fn();

jest.mock('../../Snackbar/useSnackbar', () => ({
  __esModule: true,
  default: jest
    .fn()
    .mockImplementation(() => ({ showMessage: mockedShowMessage })),
}));

interface Result {
  data: string;
}

const request = jest.fn();

const renderUseRequest = (
  requestParams: RequestParams<Result>,
): RenderHookResult<unknown, RequestResult<Result>> =>
  renderHook(() => useRequest(requestParams));

describe(useRequest, () => {
  afterEach(() => {
    request.mockReset();
    mockedShowMessage.mockReset();
    mockedAxios.isCancel.mockReset();
  });

  it('resolves with the result of the given request', async () => {
    request.mockImplementation(() => jest.fn().mockResolvedValue('success'));

    const { result } = renderUseRequest({
      request,
    });

    await act(async () =>
      result.current.sendRequest().then((data) => {
        expect(data).toEqual('success');
      }),
    );
  });

  it('shows an error via the Snackbar using the result of the given getErrorMessage function when it is passed', async () => {
    request.mockImplementation(() => jest.fn().mockRejectedValue({}));

    const getErrorMessage = (): string => 'custom message';

    const { result } = renderUseRequest({ request, getErrorMessage });

    await act(async () => {
      result.current.sendRequest();
    });

    expect(mockedShowMessage).toHaveBeenCalledWith({
      message: 'custom message',
      severity: Severity.error,
    });
  });

  it('shows an error via the Snackbar using the error message from the API when available', async () => {
    request.mockImplementation(() =>
      jest.fn().mockRejectedValue({
        response: { data: { message: 'failure' } },
      }),
    );

    const { result } = renderUseRequest({ request });

    await act(async () => {
      result.current.sendRequest();
    });

    expect(mockedShowMessage).toHaveBeenCalledWith({
      message: 'failure',
      severity: Severity.error,
    });
  });

  it('shows a default failure message via the Snackbar as fallback', async () => {
    request.mockImplementation(() => jest.fn().mockRejectedValue({}));

    const { result } = renderUseRequest({
      request,
      defaultFailureMessage: 'Oops',
    });

    await act(async () => {
      result.current.sendRequest();
    });

    expect(mockedShowMessage).toHaveBeenCalledWith({
      message: 'Oops',
      severity: Severity.error,
    });
  });

  it('does not show any message via the Snackbar when the error is an axios cancel', async () => {
    mockedAxios.isCancel.mockReturnValue(true);

    request.mockImplementation(() => jest.fn().mockRejectedValue({}));

    const { result } = renderUseRequest({
      request,
    });

    await act(async () => {
      result.current.sendRequest();
    });

    expect(mockedShowMessage).not.toHaveBeenCalled();
  });
});
