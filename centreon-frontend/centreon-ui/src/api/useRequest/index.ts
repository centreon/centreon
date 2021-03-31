import * as React from 'react';

import axios from 'axios';
import { pathOr, cond, T, defaultTo } from 'ramda';
import ulog from 'ulog';
import { JsonDecoder } from 'ts.data.json';

import useCancelTokenSource from '../useCancelTokenSource';
import Severity from '../../Snackbar/Severity';
import useSnackbar from '../../Snackbar/useSnackbar';

const log = ulog('API Request');

export interface RequestParams<TResult> {
  decoder?: JsonDecoder.Decoder<TResult>;
  defaultFailureMessage?: string;
  getErrorMessage?: (error) => string;
  request: (token) => (params?) => Promise<TResult>;
}

export interface RequestResult<TResult> {
  sendRequest: (params?) => Promise<TResult>;
  sending: boolean;
}

const useRequest = <TResult>({
  request,
  decoder,
  getErrorMessage,
  defaultFailureMessage = 'Oops, something went wrong',
}: RequestParams<TResult>): RequestResult<TResult> => {
  const { token, cancel } = useCancelTokenSource();
  const { showMessage } = useSnackbar();

  const [sending, setSending] = React.useState(false);

  React.useEffect(() => {
    return (): void => cancel();
  }, []);

  const showErrorMessage = (error): void => {
    const message = defaultTo(
      pathOr(defaultFailureMessage, ['response', 'data', 'message']),
      getErrorMessage,
    )(error);

    showMessage({
      message,
      severity: Severity.error,
    });
  };

  const sendRequest = (params): Promise<TResult> => {
    setSending(true);

    return request(token)(params)
      .then((data) => {
        if (decoder) {
          return decoder.decodePromise(data);
        }
        return data;
      })
      .catch((error) => {
        log.error(error);

        cond([
          [axios.isCancel, T],
          [T, showErrorMessage],
        ])(error);

        throw error;
      })
      .finally(() => setSending(false));
  };

  return { sendRequest, sending };
};

export default useRequest;
