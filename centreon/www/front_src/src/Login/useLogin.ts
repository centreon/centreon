import { useCallback, useEffect } from 'react';

import { FormikHelpers, FormikValues } from 'formik';
import { useAtom, useSetAtom } from 'jotai';
import {
  equals,
  filter,
  isEmpty,
  isNil,
  not,
  prop,
  propEq,
  reject
} from 'ramda';
import { useCookies } from 'react-cookie';
import { useTranslation } from 'react-i18next';
import { useNavigate, useSearchParams } from 'react-router';

import { ResponseError, useFetchQuery, useSnackbar } from '@centreon/ui';

import { platformInstallationStatusAtom } from '../Main/atoms/platformInstallationStatusAtom';
import useInitializeTranslation from '../Main/useInitializeTranslation';
import useUser from '../Main/useUser';
import { passwordResetInformationsAtom } from '../ResetPassword/passwordResetInformationsAtom';
import { PlatformInstallationStatus } from '../api/models';
import routeMap from '../reactRoutes/routeMap';

import { providersConfigurationDecoder } from './api/decoder';
import { providersConfigurationEndpoint } from './api/endpoint';
import {
  LoginFormValues,
  ProviderConfiguration,
  Redirect,
  RedirectAPI
} from './models';
import {
  labelLoginSucceeded,
  labelPasswordHasExpired
} from './translatedLabels';
import usePostLogin from './usePostLogin';

interface UseLoginState {
  authenticationError: string | null;
  hasForcedProvider: boolean;
  platformInstallationStatus: PlatformInstallationStatus | null;
  providersConfiguration: Array<ProviderConfiguration> | null;
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
  propEq('local', 'name')
);

const getActiveProviders = filter<ProviderConfiguration>(
  propEq(true, 'isActive')
);

export const router = {
  useNavigate,
  useSearchParams
};

const useLogin = (): UseLoginState => {
  const { t, i18n } = useTranslation();
  const { sendLogin } = usePostLogin();
  const [searchParams] = router.useSearchParams();

  const [cookies] = useCookies(['REDIRECT_URI']);

  const { data: providers } = useFetchQuery<Array<ProviderConfiguration>>({
    decoder: providersConfigurationDecoder,
    getEndpoint: () => providersConfigurationEndpoint,
    getQueryKey: () => ['providerConfiguration'],
    queryOptions: {
      refetchOnMount: false,
      suspense: false
    }
  });
  const [platformInstallationStatus] = useAtom(platformInstallationStatusAtom);

  const { getInternalTranslation, getExternalTranslation } =
    useInitializeTranslation();

  const { showSuccessMessage, showWarningMessage, showErrorMessage } =
    useSnackbar();
  const navigate = router.useNavigate();
  const loadUser = useUser();

  const setPasswordResetInformations = useSetAtom(
    passwordResetInformationsAtom
  );

  const checkPasswordExpiration = useCallback(
    ({ error, alias, setSubmitting }) => {
      const isUserNotAllowed = propEq(401, 'statusCode', error);

      const { password_is_expired: passwordIsExpired } = prop(
        'additionalInformation',
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
      showErrorMessage(prop('message', error) as string);
    },
    []
  );

  const submitLoginForm = (
    values: LoginFormValues,
    { setSubmitting }
  ): void => {
    sendLogin({
      payload: {
        login: values.alias,
        password: values.password
      }
    })
      .then((response) => {
        if ((response as ResponseError).isError) {
          checkPasswordExpiration({
            alias: values.alias,
            error: response as ResponseError,
            setSubmitting
          });

          return;
        }
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

  const authenticationError = searchParams.get('authenticationError');

  useEffect(() => {
    if (!prop('REDIRECT_URI', cookies)) {
      return;
    }
    navigate(cookies.REDIRECT_URI);
  }, [cookies]);

  useEffect(() => {
    if (isEmpty(forcedProviders) || authenticationError) {
      return;
    }

    window.location.replace(forcedProviders[0].authenticationUri);
  }, [forcedProviders, authenticationError]);

  return {
    authenticationError,
    hasForcedProvider: !!forcedProviders,
    platformInstallationStatus,
    providersConfiguration: activeProviders,
    submitLoginForm
  };
};

export default useLogin;
