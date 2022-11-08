import * as React from 'react';

import 'ulog';
import axios from 'axios';
import { pathOr, defaultTo } from 'ramda';
import anylogger from 'anylogger';
import { JsonDecoder } from 'ts.data.json';

import useCancelTokenSource from '../useCancelTokenSource';
import useSnackbar from '../../Snackbar/useSnackbar';

const log = anylogger('API Request');

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
  const { showErrorMessage } = useSnackbar();

  const [sending, setSending] = React.useState(false);

  React.useEffect(() => {
    return (): void => cancel();
  }, []);

  const showRequestErrorMessage = (error): void => {
    log.error(error);
    const message = defaultTo(
      pathOr(defaultFailureMessage, ['response', 'data', 'message']),
      getErrorMessage,
    )(error);

    showErrorMessage(message);
  };

  const sendRequest = (params): Promise<TResult> => {
    setSending(true);

    return request(token)(params)
      .then((data) => {
        if (decoder) {
          return decoder.decodeToPromise(data);
        }

        return data;
      })
      .catch((error) => {
        if (axios.isCancel(error)) {
          log.warn(error);

          throw error;
        }

        showRequestErrorMessage(error);

        throw error;
      })
      .finally(() => setSending(false));
  };

  return { sendRequest, sending };
};

export default useRequest;
