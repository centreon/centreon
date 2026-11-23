import { useCallback } from 'react';
import { useNavigate } from 'react-router';

import { getServicesOfHostPath, servicesPagePath } from '../Services/utils';
import { useIsPageAllowed } from '../useIsPageAllowed';
import { getHostServicesUrl } from './utils';

export const useGoToServices = (): ((hostName: string) => void) => {
  const navigate = useNavigate();
  const isServicesPageAllowed = useIsPageAllowed(servicesPagePath);

  return useCallback(
    (hostName: string): void => {
      if (isServicesPageAllowed) {
        navigate(getServicesOfHostPath(hostName));

        return;
      }

      window.open(getHostServicesUrl(hostName), '_self');
    },
    [isServicesPageAllowed, navigate]
  );
};
