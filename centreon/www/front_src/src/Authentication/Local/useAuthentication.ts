import { useEffect, useState } from 'react';

import { useRequest } from '@centreon/ui';

import { getPasswordPasswordSecurityPolicy } from '../api';
import { adaptPasswordSecurityPolicyFromAPI } from '../api/adapters';
import { securityPolicyDecoder } from '../api/decoders';

import { PasswordSecurityPolicy } from './models';

interface UseAuthenticationState {
  initialPasswordPasswordSecurityPolicy: PasswordSecurityPolicy | null;
  loadPasswordPasswordSecurityPolicy: () => void;
  sendingGetPasswordPasswordSecurityPolicy: boolean;
}

const useAuthentication = (): UseAuthenticationState => {
  const [
    initialPasswordPasswordSecurityPolicy,
    setInitialPasswordSecurityPolicy
  ] = useState<PasswordSecurityPolicy | null>(null);
  const { sendRequest, sending } = useRequest<PasswordSecurityPolicy>({
    decoder: securityPolicyDecoder,
    request: getPasswordPasswordSecurityPolicy
  });

  const loadPasswordPasswordSecurityPolicy = (): void => {
    sendRequest()
      .then((securityPolicy) =>
        setInitialPasswordSecurityPolicy(
          adaptPasswordSecurityPolicyFromAPI(securityPolicy)
        )
      )
      .catch(() => undefined);
  };

  useEffect(() => {
    loadPasswordPasswordSecurityPolicy();
  }, []);

  return {
    initialPasswordPasswordSecurityPolicy,
    loadPasswordPasswordSecurityPolicy,
    sendingGetPasswordPasswordSecurityPolicy: sending
  };
};

export default useAuthentication;
