import { useEffect, useRef } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { path, equals, not, pathEq } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate, useSearchParams } from 'react-router-dom';

import type { Actions } from '@centreon/ui';
import { getData, postData, useRequest, useSnackbar } from '@centreon/ui';
import {
  acknowledgementAtom,
  aclAtom,
  downtimeAtom,
  platformNameAtom,
  platformVersionsAtom,
  refreshIntervalAtom
} from '@centreon/ui-context';

import { loginPageCustomisationEndpoint } from '../Login/api/endpoint';
import { areUserParametersLoadedAtom } from '../Main/useUser';
import useNavigation from '../Navigation/useNavigation';
import { logoutEndpoint } from '../api/endpoint';
import reactRoutes from '../reactRoutes/routeMap';

import { aclEndpoint, parametersEndpoint } from './endpoint';
import { CustomLoginPlatform, DefaultParameters } from './models';
import { labelYouAreDisconnected } from './translatedLabels';
import usePendo from './usePendo';

const keepAliveEndpoint =
  './api/internal.php?object=centreon_keepalive&action=keepAlive';

interface UseAppState {
  hasMinArgument: () => boolean;
}

const useApp = (): UseAppState => {
  const { t } = useTranslation();

  const keepAliveIntervalRef = useRef<NodeJS.Timer | null>(null);
  usePendo();

  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  const { showErrorMessage } = useSnackbar();

  const { sendRequest: keepAliveRequest } = useRequest({
    httpCodesBypassErrorSnackbar: [401],
    request: getData
  });
  const { sendRequest: getParameters } = useRequest<DefaultParameters>({
    httpCodesBypassErrorSnackbar: [403],
    request: getData
  });
  const { sendRequest: getAcl } = useRequest<Actions>({
    request: getData
  });

  const { sendRequest: logoutRequest } = useRequest({
    request: postData
  });

  const { sendRequest: getCustomPlatformRequest } =
    useRequest<CustomLoginPlatform>({
      httpCodesBypassErrorSnackbar: [404, 401],
      request: getData
    });

  const [platformVersion] = useAtom(platformVersionsAtom);
  const setDowntime = useSetAtom(downtimeAtom);
  const setRefreshInterval = useSetAtom(refreshIntervalAtom);
  const setAcl = useSetAtom(aclAtom);
  const setAcknowledgement = useSetAtom(acknowledgementAtom);
  const setAreUserParametersLoaded = useSetAtom(areUserParametersLoadedAtom);

  const setPlaformName = useSetAtom(platformNameAtom);

  const { getNavigation } = useNavigation();

  const logout = (): void => {
    setAreUserParametersLoaded(false);
    logoutRequest({
      data: {},
      endpoint: logoutEndpoint
    }).then(() => {
      showErrorMessage(t(labelYouAreDisconnected));
      navigate(reactRoutes.login);
    });
  };

  useEffect(() => {
    getNavigation();

    getParameters({
      endpoint: parametersEndpoint
    })
      .then((retrievedParameters) => {
        setDowntime({
          duration: Number.parseInt(
            retrievedParameters.monitoring_default_downtime_duration,
            10
          ),
          fixed: retrievedParameters.monitoring_default_downtime_fixed,
          with_services:
            retrievedParameters.monitoring_default_downtime_with_services
        });
        setRefreshInterval(
          Number.parseInt(
            retrievedParameters.monitoring_default_refresh_interval,
            10
          )
        );
        setAcknowledgement({
          force_active_checks:
            retrievedParameters.monitoring_default_acknowledgement_force_active_checks,
          notify: retrievedParameters.monitoring_default_acknowledgement_notify,
          persistent:
            retrievedParameters.monitoring_default_acknowledgement_persistent,
          sticky: retrievedParameters.monitoring_default_acknowledgement_sticky,
          with_services:
            retrievedParameters.monitoring_default_acknowledgement_with_services
        });
      })
      .catch((error) => {
        if (pathEq(401, ['response', 'status'])(error)) {
          logout();
        }
      });

    getAcl({
      endpoint: aclEndpoint
    })
      .then((retrievedAcl) => {
        setAcl({ actions: retrievedAcl });
      })
      .catch((error) => {
        if (pathEq(401, ['response', 'status'])(error)) {
          logout();
        }
      });

    if (path(['modules', 'centreon-it-edition-extensions'], platformVersion)) {
      getCustomPlatformRequest({
        endpoint: loginPageCustomisationEndpoint
      })
        .then(({ platform_name }) => setPlaformName(platform_name))
        .catch(() => undefined);
    }
  }, []);

  const hasMinArgument = (): boolean => equals(searchParams.get('min'), '1');

  const keepAlive = (): void => {
    keepAliveRequest({
      endpoint: keepAliveEndpoint
    }).catch((error) => {
      if (not(pathEq(401, ['response', 'status'], error))) {
        return;
      }
      logout();

      clearInterval(keepAliveIntervalRef.current as NodeJS.Timer);
    });
  };

  useEffect(() => {
    keepAlive();

    keepAliveIntervalRef.current = setInterval(keepAlive, 15000);

    return (): void => {
      clearInterval(keepAliveIntervalRef.current as NodeJS.Timer);
    };
  }, []);

  return {
    hasMinArgument
  };
};

export default useApp;
