import { useCallback, useEffect } from 'react';

import { FormikHelpers, FormikValues } from 'formik';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import {
  path,
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
import { useNavigate, useSearchParams } from 'react-router-dom';

import { ResponseError, useFetchQuery, useSnackbar } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import { platformInstallationStatusAtom } from '../Main/atoms/platformInstallationStatusAtom';
import useInitializeTranslation from '../Main/useInitializeTranslation';
import useUser from '../Main/useUser';
import { passwordResetInformationsAtom } from '../ResetPassword/passwordResetInformationsAtom';
import { PlatformInstallationStatus } from '../api/models';
import centreonLogo from '../assets/logo-centreon-colors.svg';
import routeMap from '../reactRoutes/routeMap';

import {
  loginPageCustomisationDecoder,
  providersConfigurationDecoder
} from './api/decoder';
import {
  loginPageCustomisationEndpoint,
  providersConfigurationEndpoint
} from './api/endpoint';
import {
  LoginFormValues,
  LoginPageCustomisation,
  ProviderConfiguration,
  Redirect,
  RedirectAPI
} from './models';
import {
  labelLoginSucceeded,
  labelPasswordHasExpired
} from './translatedLabels';
import usePostLogin from './usePostLogin';
import useWallpaper from './useWallpaper';

interface UseLoginState {
  authenticationError: string | null;
  hasForcedProvider: boolean;
  loginPageCustomisation: LoginPageCustomisation;
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

const defaultLoginPageCustomisation: LoginPageCustomisation = {
  customText: null,
  iconSource: centreonLogo,
  imageSource: null,
  platformName: null,
  textPosition: null
};

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
  const platformVersions = useAtomValue(platformVersionsAtom);
  const { data: loginPageCustomisationData, isFetching } =
    useFetchQuery<LoginPageCustomisation>({
      decoder: loginPageCustomisationDecoder,
      getEndpoint: () => loginPageCustomisationEndpoint,
      getQueryKey: () => ['loginPageCustomisation'],
      httpCodesBypassErrorSnackbar: [404, 401],
      queryOptions: {
        enabled: !!path(
          ['modules', 'centreon-it-edition-extensions'],
          platformVersions
        ),
        retry: false,
        suspense: false
      }
    });

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

  const wallpaper = useWallpaper();

  const authenticationError = searchParams.get('authenticationError');

  const loginPageCustomisation = isFetching
    ? defaultLoginPageCustomisation
    : {
        customText:
          loginPageCustomisationData?.customText ||
          defaultLoginPageCustomisation.customText,
        iconSource:
          loginPageCustomisationData?.iconSource ||
          defaultLoginPageCustomisation.iconSource,
        imageSource: loginPageCustomisationData?.imageSource || wallpaper,
        platformName:
          loginPageCustomisationData?.platformName ||
          defaultLoginPageCustomisation.platformName,
        textPosition:
          loginPageCustomisationData?.textPosition ||
          defaultLoginPageCustomisation.textPosition
      };

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
    loginPageCustomisation,
    platformInstallationStatus,
    providersConfiguration: activeProviders,
    submitLoginForm
  };
};

export default useLogin;
