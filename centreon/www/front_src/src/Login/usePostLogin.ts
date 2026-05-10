// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { redirectDecoder } from './api/decoder';
import { loginEndpoint } from './api/endpoint';
import { Redirect } from './models';

interface UsePostLoginState {
  sendLogin: ({
    payload
  }: {
    payload: { login: string; password: string };
  }) => Promise<Redirect | ResponseError>;
}

const usePostLogin = (): UsePostLoginState => {
  const { mutateAsync: sendLogin } = useMutationQuery({
    decoder: redirectDecoder,
    getEndpoint: () => loginEndpoint,
    httpCodesBypassErrorSnackbar: [401],
    method: Method.POST
  });

  return { sendLogin };
};

export default usePostLogin;
