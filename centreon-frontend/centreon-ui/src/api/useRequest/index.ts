import * as React from 'react';

import axios from 'axios';
import { pathOr, cond, T, defaultTo } from 'ramda';

import useCancelTokenSource from '../useCancelTokenSource';
import Severity from '../../Snackbar/Severity';
import useSnackbar from '../../Snackbar/useSnackbar';

export interface RequestParams<TResult> {
  request: (token) => (params?) => Promise<TResult>;
  getErrorMessage?: (error) => string;
  defaultFailureMessage?: string;
}

export interface RequestResult<TResult> {
  sendRequest: (params?) => Promise<TResult>;
  sending: boolean;
}

const useRequest = <TResult>({
  request,
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
      .catch((error) =>
        cond([
          [axios.isCancel, T],
          [T, showErrorMessage],
        ])(error),
      )
      .finally(() => setSending(false));
  };

  return { sendRequest, sending };
};

export default useRequest;
