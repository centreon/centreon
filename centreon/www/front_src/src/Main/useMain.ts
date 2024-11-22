import { useEffect } from 'react';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { and, includes, isEmpty, isNil, not, or } from 'ramda';
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom';

import { getData, useRequest, useSnackbar } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { platformInstallationStatusDecoder } from '../api/decoders';
import { platformInstallationStatusEndpoint } from '../api/endpoint';
import { PlatformInstallationStatus } from '../api/models';
import useFederatedModules from '../federatedModules/useFederatedModules';
import useFederatedWidgets from '../federatedModules/useFederatedWidgets';
import reactRoutes from '../reactRoutes/routeMap';

import { platformInstallationStatusAtom } from './atoms/platformInstallationStatusAtom';
import useInitializeTranslation from './useInitializeTranslation';
import usePlatformFeatures from './usePlatformFeatures';
import usePlatformVersions from './usePlatformVersions';
import useUser, { areUserParametersLoadedAtom } from './useUser';

export const router = {
  useNavigate
};

const useMain = (hasReachedAPublicPage: boolean): void => {
  const { sendRequest: getPlatformInstallationStatus } =
    useRequest<PlatformInstallationStatus>({
      decoder: platformInstallationStatusDecoder,
      request: getData
    });
  const { showErrorMessage } = useSnackbar();

  const {
    getBrowserLocale,
    getInternalTranslation,
    getExternalTranslation,
    i18next
  } = useInitializeTranslation();

  const [areUserParametersLoaded, setAreUserParametersLoaded] = useAtom(
    areUserParametersLoadedAtom
  );
  const user = useAtomValue(userAtom);

  const setPlatformInstallationStatus = useSetAtom(
    platformInstallationStatusAtom
  );

  const loadUser = useUser();
  const location = useLocation();
  const navigate = router.useNavigate();
  const [searchParameter] = useSearchParams();
  const { getPlatformVersions } = usePlatformVersions();
  const { getPlatformFeatures } = usePlatformFeatures();
  useFederatedModules();
  useFederatedWidgets();

  const displayAuthenticationError = (): void => {
    const authenticationError = searchParameter.get('authenticationError');

    if (or(isNil(authenticationError), isEmpty(authenticationError))) {
      return;
    }

    showErrorMessage(authenticationError as string);
  };

  useEffect(() => {
    displayAuthenticationError();

    if (hasReachedAPublicPage) {
      setAreUserParametersLoaded(false);
      getPlatformVersions();
      getExternalTranslation();

      return;
    }

    getPlatformInstallationStatus({
      endpoint: platformInstallationStatusEndpoint
    }).then((retrievedPlatformInstallationStatus) => {
      setPlatformInstallationStatus(retrievedPlatformInstallationStatus);

      if (
        !retrievedPlatformInstallationStatus?.isInstalled ||
        retrievedPlatformInstallationStatus.hasUpgradeAvailable
      ) {
        setAreUserParametersLoaded(false);

        return;
      }
      loadUser();

      getPlatformFeatures();
      getPlatformVersions();
    });
  }, []);

  useEffect((): void => {
    if (not(areUserParametersLoaded)) {
      return;
    }

    getInternalTranslation();
  }, [areUserParametersLoaded]);

  useEffect(() => {
    const canChangeToBrowserLanguage = and(
      isNil(areUserParametersLoaded),
      i18next.isInitialized
    );
    if (canChangeToBrowserLanguage) {
      i18next?.changeLanguage(getBrowserLocale());
    }

    const canRedirectToUserDefaultPage = and(
      areUserParametersLoaded,
      includes(location.pathname, [reactRoutes.login, '/'])
    );

    if (not(canRedirectToUserDefaultPage)) {
      return;
    }

    navigate(user.default_page as string);
  }, [location, areUserParametersLoaded, user]);
};

export default useMain;
