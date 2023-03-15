import { useRequest } from '@centreon/ui';

import { Redirect } from './models';
import { redirectDecoder } from './api/decoder';
import postLogin from './api';

interface UsePostLoginState {
  sendLogin: (values) => Promise<Redirect>;
}

const usePostLogin = (): UsePostLoginState => {
  const { sendRequest: sendLogin } = useRequest<Redirect>({
    decoder: redirectDecoder,
    httpCodesBypassErrorSnackbar: [401],
    request: postLogin
  });

  return { sendLogin };
};

export default usePostLogin;
