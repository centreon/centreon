import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { Redirect } from './models';
import { redirectDecoder } from './api/decoder';
import { loginEndpoint } from './api/endpoint';

interface UsePostLoginState {
  sendLogin: (payload: unknown) => Promise<Redirect | ResponseError>;
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
