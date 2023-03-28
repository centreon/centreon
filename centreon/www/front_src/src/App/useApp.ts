import { useEffect, useRef } from 'react';

import { useUpdateAtom } from 'jotai/utils';
import { equals, not, pathEq } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useSetAtom } from 'jotai';

import { getData, postData, useRequest, useSnackbar } from '@centreon/ui';
import {
  acknowledgementAtom,
  aclAtom,
  downtimeAtom,
  platformNameAtom,
  refreshIntervalAtom,
  userAtom
} from '@centreon/ui-context';
import type { Actions } from '@centreon/ui';

import { logoutEndpoint } from '../api/endpoint';
import { areUserParametersLoadedAtom } from '../Main/useUser';
import useNavigation from '../Navigation/useNavigation';
import reactRoutes from '../reactRoutes/routeMap';
import { loginPageCustomisationEndpoint } from '../Login/api/endpoint';

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
      httpCodesBypassErrorSnackbar: [404],
      request: getData
    });

  const setUser = useUpdateAtom(userAtom);
  const setDowntime = useUpdateAtom(downtimeAtom);
  const setRefreshInterval = useUpdateAtom(refreshIntervalAtom);
  const setAcl = useUpdateAtom(aclAtom);
  const setAcknowledgement = useUpdateAtom(acknowledgementAtom);
  const setAreUserParametersLoaded = useUpdateAtom(areUserParametersLoadedAtom);

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

    Promise.all([
      getParameters({
        endpoint: parametersEndpoint
      }),
      getAcl({
        endpoint: aclEndpoint
      }),
      getCustomPlatformRequest({
        endpoint: loginPageCustomisationEndpoint
      })
    ])
      .then(([retrievedParameters, retrievedAcl, customLoginPlatform]) => {
        setDowntime({
          duration: parseInt(
            retrievedParameters.monitoring_default_downtime_duration,
            10
          ),
          fixed: retrievedParameters.monitoring_default_downtime_fixed,
          with_services:
            retrievedParameters.monitoring_default_downtime_with_services
        });
        setRefreshInterval(
          parseInt(retrievedParameters.monitoring_default_refresh_interval, 10)
        );
        setAcl({ actions: retrievedAcl });
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
        setUser((currentUser) => ({
          ...currentUser,
          resourceStatusViewMode: retrievedParameters.resource_status_view_mode
        }));
        setPlaformName(customLoginPlatform.platform_name);
      })
      .catch((error) => {
        if (pathEq(['response', 'status'], 401)(error)) {
          logout();
        }
      });
  }, []);

  const hasMinArgument = (): boolean => equals(searchParams.get('min'), '1');

  const keepAlive = (): void => {
    keepAliveRequest({
      endpoint: keepAliveEndpoint
    }).catch((error) => {
      if (not(pathEq(['response', 'status'], 401, error))) {
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
