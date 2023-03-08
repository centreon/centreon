import { useCallback, useEffect } from 'react';

import { useNavigate } from 'react-router-dom';
import { FormikHelpers, FormikValues } from 'formik';
import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import {
  filter,
  isEmpty,
  isNil,
  not,
  propEq,
  reject,
  path,
  pathEq,
  equals,
  prop
} from 'ramda';
import { useUpdateAtom } from 'jotai/utils';

import {
  useSnackbar,
  useFetchQuery,
  Method,
  useMutationQuery,
  ResponseError
} from '@centreon/ui';

import { PlatformInstallationStatus } from '../api/models';
import { platformInstallationStatusAtom } from '../Main/atoms/platformInstallationStatusAtom';
import useUser from '../Main/useUser';
import { passwordResetInformationsAtom } from '../ResetPassword/passwordResetInformationsAtom';
import routeMap from '../reactRoutes/routeMap';
import useInitializeTranslation from '../Main/useInitializeTranslation';

import {
  loginConfigurationDecoder,
  providersConfigurationDecoder,
  redirectDecoder
} from './api/decoder';
import {
  labelLoginSucceeded,
  labelPasswordHasExpired
} from './translatedLabels';
import {
  loginConfigurationEndpoints,
  loginEndpoint,
  providersConfigurationEndpoint
} from './api/endpoint';
import {
  LoginFormValues,
  Redirect,
  RedirectAPI,
  ProviderConfiguration,
  LoginConfiguration
} from './models';

interface UseLoginState {
  loginConfiguration: LoginConfiguration;
  platformInstallationStatus: PlatformInstallationStatus | null;
  providersConfiguration: Array<ProviderConfiguration> | null;
  sendLogin: (payload: unknown) => Promise<Redirect | ResponseError>;
  submitLoginForm: (
    values: LoginFormValues,
    { setSubmitting }: Pick<FormikHelpers<FormikValues>, 'setSubmitting'>
  ) => void;
}

const getForcedProviders = filter<ProviderConfiguration>(
  (provider): boolean =>
    not(isNil(provider.isForced)) &&
    (provider.isForced as boolean) &&
    not(equals(provider.name, 'local'))
);

const getExternalProviders = reject<ProviderConfiguration>(
  propEq('name', 'local')
);

const getActiveProviders = filter<ProviderConfiguration>(
  propEq('isActive', true)
);

const useLogin = (): UseLoginState => {
  const { t, i18n } = useTranslation();

  const { mutateAsync: sendLogin } = useMutationQuery({
    decoder: redirectDecoder,
    getEndpoint: () => loginEndpoint,
    httpCodesBypassErrorSnackbar: [401],
    method: Method.POST
  });

  const { data: providers } = useFetchQuery<Array<ProviderConfiguration>>({
    decoder: providersConfigurationDecoder,
    getEndpoint: () => providersConfigurationEndpoint,
    getQueryKey: () => ['providerConfiguration'],
    queryOptions: {
      refetchOnMount: false,
      suspense: false
    }
  });

  const { data: loginConfigurationData } = useFetchQuery<LoginConfiguration>({
    decoder: loginConfigurationDecoder,
    getEndpoint: () => loginConfigurationEndpoints,
    getQueryKey: () => ['loginConfiguration'],
    httpCodesBypassErrorSnackbar: [404],
    queryOptions: {
      retry: false,
      suspense: false
    }
  });

  const { getInternalTranslation, getExternalTranslation } =
    useInitializeTranslation();

  const { showSuccessMessage, showWarningMessage, showErrorMessage } =
    useSnackbar();
  const navigate = useNavigate();
  const loadUser = useUser();

  const [platformInstallationStatus] = useAtom(platformInstallationStatusAtom);
  const setPasswordResetInformations = useUpdateAtom(
    passwordResetInformationsAtom
  );

  const checkPasswordExpiration = useCallback(
    ({ error, alias, setSubmitting }) => {
      const isUserNotAllowed = pathEq(['response', 'status'], 401, error);

      const { password_is_expired: passwordIsExpired } = path(
        ['response', 'data'],
        error
      ) as RedirectAPI;

      if (isUserNotAllowed && passwordIsExpired) {
        setPasswordResetInformations({
          alias
        });
        navigate(routeMap.resetPassword);
        showWarningMessage(t(labelPasswordHasExpired));

        return;
      }

      setSubmitting(false);
      showErrorMessage(path(['response', 'data', 'message'], error) as string);
    },
    []
  );

  const submitLoginForm = (
    values: LoginFormValues,
    { setSubmitting }
  ): void => {
    sendLogin({
      login: values.alias,
      password: values.password
    })
      .then((response) => {
        showSuccessMessage(t(labelLoginSucceeded));
        getInternalTranslation().then(() =>
          loadUser()?.then(() =>
            navigate(prop('redirectUri', response as Redirect))
          )
        );
      })
      .catch((error) =>
        checkPasswordExpiration({ alias: values.alias, error, setSubmitting })
      );
  };

  const getBrowserLocale = (): string => navigator.language.slice(0, 2);

  useEffect(() => {
    getExternalTranslation().then(() =>
      i18n.changeLanguage?.(getBrowserLocale())
    );
  }, []);

  const forcedProviders = getForcedProviders(providers || []);

  const externalProviders = getExternalProviders(providers || []);

  const activeProviders = getActiveProviders(externalProviders || []);

  const initialConfiguration: LoginConfiguration = {
    customText: null,
    iconSource: null,
    imageSource: null,
    platformName: null,
    textPosition: null
  };

  const loginConfiguration = loginConfigurationData || initialConfiguration;

  useEffect(() => {
    if (not(isEmpty(forcedProviders))) {
      window.location.replace(forcedProviders[0].authenticationUri);
    }
  }, [forcedProviders]);

  return {
    loginConfiguration,
    platformInstallationStatus,
    providersConfiguration: activeProviders,
    sendLogin,
    submitLoginForm
  };
};

export default useLogin;
