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
  equals,
  prop
} from 'ramda';
import { useUpdateAtom } from 'jotai/utils';

import { platformNameAtom } from '@centreon/ui-context';
import { useSnackbar, useFetchQuery, ResponseError } from '@centreon/ui';

import { PlatformInstallationStatus } from '../api/models';
import { platformInstallationStatusAtom } from '../Main/atoms/platformInstallationStatusAtom';
import useUser from '../Main/useUser';
import { passwordResetInformationsAtom } from '../ResetPassword/passwordResetInformationsAtom';
import routeMap from '../reactRoutes/routeMap';
import useInitializeTranslation from '../Main/useInitializeTranslation';
import centreonLogo from '../assets/logo-centreon-colors.svg';

import {
  loginPageCustomisationDecoder,
  providersConfigurationDecoder
} from './api/decoder';
import {
  labelLoginSucceeded,
  labelPasswordHasExpired
} from './translatedLabels';
import {
  loginPageCustomisationEndpoint,
  providersConfigurationEndpoint
} from './api/endpoint';
import {
  LoginFormValues,
  Redirect,
  RedirectAPI,
  ProviderConfiguration,
  LoginPageCustomisation
} from './models';
import usePostLogin from './usePostLogin';
import useWallpaper from './useWallpaper';

interface UseLoginState {
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
  propEq('name', 'local')
);

const getActiveProviders = filter<ProviderConfiguration>(
  propEq('isActive', true)
);

const defaultLoginPageCustomisation: LoginPageCustomisation = {
  customText: null,
  iconSource: null,
  imageSource: null,
  platformName: null,
  textPosition: null
};

export const router = {
  useNavigate
};

const useLogin = (): UseLoginState => {
  const { t, i18n } = useTranslation();

  const { sendLogin } = usePostLogin();

  const { data: providers } = useFetchQuery<Array<ProviderConfiguration>>({
    decoder: providersConfigurationDecoder,
    getEndpoint: () => providersConfigurationEndpoint,
    getQueryKey: () => ['providerConfiguration'],
    queryOptions: {
      refetchOnMount: false,
      suspense: false
    }
  });

  const { data: loginPageCustomisationData, isLoading } =
    useFetchQuery<LoginPageCustomisation>({
      decoder: loginPageCustomisationDecoder,
      getEndpoint: () => loginPageCustomisationEndpoint,
      getQueryKey: () => ['loginPageCustomisation'],
      httpCodesBypassErrorSnackbar: [404],
      queryOptions: {
        retry: false,
        suspense: false
      }
    });

  const { getInternalTranslation, getExternalTranslation } =
    useInitializeTranslation();

  const wallpaper = useWallpaper();

  const { showSuccessMessage, showWarningMessage, showErrorMessage } =
    useSnackbar();
  const navigate = router.useNavigate();
  const loadUser = useUser();

  const [platformInstallationStatus] = useAtom(platformInstallationStatusAtom);
  const setPasswordResetInformations = useUpdateAtom(
    passwordResetInformationsAtom
  );
  const setPlatformName = useUpdateAtom(platformNameAtom);

  const checkPasswordExpiration = useCallback(
    ({ error, alias, setSubmitting }) => {
      const isUserNotAllowed = propEq('statusCode', 401, error);

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
      login: values.alias,
      password: values.password
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

        if (
          loginPageCustomisationData &&
          loginPageCustomisationData.platformName
        ) {
          setPlatformName(loginPageCustomisationData);
        }

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

  const loginPageCustomisation: LoginPageCustomisation = isLoading
    ? defaultLoginPageCustomisation
    : {
        customText:
          loginPageCustomisationData?.customText ||
          defaultLoginPageCustomisation.customText,
        iconSource: loginPageCustomisationData?.iconSource || centreonLogo,
        imageSource: loginPageCustomisationData?.imageSource || wallpaper,
        platformName:
          loginPageCustomisationData?.platformName ||
          defaultLoginPageCustomisation.platformName,
        textPosition:
          loginPageCustomisationData?.textPosition ||
          defaultLoginPageCustomisation.textPosition
      };

  useEffect(() => {
    if (isEmpty(forcedProviders)) {
      return;
    }

    window.location.replace(forcedProviders[0].authenticationUri);
  }, [forcedProviders]);

  return {
    loginPageCustomisation,
    platformInstallationStatus,
    providersConfiguration: activeProviders,
    submitLoginForm
  };
};

export default useLogin;
