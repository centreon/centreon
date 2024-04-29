import { useState, useEffect } from 'react';

import axios from 'axios';
import { pathOr, defaultTo, path, includes, or } from 'ramda';
import { JsonDecoder } from 'ts.data.json';

import useCancelTokenSource from '../useCancelTokenSource';
import useSnackbar from '../../Snackbar/useSnackbar';
import { errorLog, warnLog } from '../logger';

export interface RequestParams<TResult> {
  decoder?: JsonDecoder.Decoder<TResult>;
  defaultFailureMessage?: string;
  getErrorMessage?: (error) => string;
  httpCodesBypassErrorSnackbar?: Array<number>;
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
  httpCodesBypassErrorSnackbar = []
}: RequestParams<TResult>): RequestResult<TResult> => {
  const { token, cancel } = useCancelTokenSource();
  const { showErrorMessage } = useSnackbar();

  const [sending, setSending] = useState(false);

  useEffect(() => {
    return (): void => cancel();
  }, []);

  const showRequestErrorMessage = (error): void => {
    errorLog(error);

    const message = or(
      pathOr(undefined, ['response', 'data', 'message'], error),
      pathOr(defaultFailureMessage, ['response', 'data'], error)
    );

    const errorMessage = defaultTo(message, getErrorMessage?.(error));

    showErrorMessage(errorMessage as string);
  };

  const sendRequest = (params): Promise<TResult> => {
    setSending(true);

    return request(token)(params)
      .then((data) => {
        setSending(false);
        if (decoder) {
          return decoder.decodeToPromise(data);
        }

        return data;
      })
      .catch((error) => {
        setSending(false);
        if (axios.isCancel(error)) {
          warnLog(error);

          throw error;
        }

        const hasACorrespondingHttpCode = includes(
          path<number>(['response', 'status'], error) as number,
          httpCodesBypassErrorSnackbar
        );

        if (hasACorrespondingHttpCode) {
          throw error;
        }

        showRequestErrorMessage(error);

        throw error;
      });
  };

  return { sendRequest, sending };
};

export default useRequest;
