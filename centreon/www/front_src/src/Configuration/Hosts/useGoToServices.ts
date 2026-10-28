import { featureFlagsDerivedAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { flatten, includes } from 'ramda';
import { useCallback } from 'react';
import { useNavigate } from 'react-router';

import useNavigation from '../../Navigation/useNavigation';
import routeMap from '../../reactRoutes/routeMap';
import { getServicesOfHostPath } from '../Services/utils';
import { getHostServicesUrl } from './utils';

// Opens the services of a host: the React Services page, filtered on that
// host, when the user can reach it (feature flag on and page granted by the
// menu ACL); the legacy listing otherwise.
const useGoToServices = (): ((hostName: string) => void) => {
  const featureFlags = useAtomValue(featureFlagsDerivedAtom);
  const { allowedPages } = useNavigation();
  const navigate = useNavigate();

  const canReachServicesPage =
    !!featureFlags?.servicesConfiguration &&
    includes(routeMap.services, flatten(allowedPages || []));

  return useCallback(
    (hostName: string): void => {
      if (canReachServicesPage) {
        navigate(getServicesOfHostPath(hostName));

        return;
      }

      window.open(getHostServicesUrl(hostName), '_self');
    },
    [canReachServicesPage, navigate]
  );
};

export default useGoToServices;
