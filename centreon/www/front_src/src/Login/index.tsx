import { Suspense, lazy, memo } from 'react';

import { Formik } from 'formik';
import { useAtomValue } from 'jotai';
import { T, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

import { FallbackPage, LoadingSkeleton, WallpaperPage } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import { MainLoaderWithoutTranslation } from '../Main/MainLoader';
import { areUserParametersLoadedAtom } from '../Main/useUser';

import CustomText from './CustomText';
import { LoginFormValues } from './models';
import {
  labelAnErrorOccurredDuringAuthentication,
  labelCentreonWallpaper,
  labelLogin,
  labelPoweredByCentreon
} from './translatedLabels';
import useLogin from './useLogin';
import useValidationSchema from './validationSchema';

const ExternalProviders = lazy(() => import('./ExternalProviders'));

const LoginForm = lazy(() => import('./Form'));
const LoginHeader = lazy(() => import('./LoginHeader'));

const useStyles = makeStyles()((theme) => ({
  copyrightSkeleton: {
    width: theme.spacing(16)
  },
  loginBackground: {
    alignItems: 'center',
    backgroundColor: 'transparent',
    display: 'flex',
    filter: 'brightness(1)',
    flexDirection: 'column',
    height: '100vh',
    justifyContent: 'center',
    rowGap: theme.spacing(2),
    width: '100%'
  },
  loginPaper: {
    alignItems: 'center',
    display: 'grid',
    flexDirection: 'column',
    justifyItems: 'center',
    minWidth: theme.spacing(30),
    padding: theme.spacing(4, 5),
    rowGap: theme.spacing(4)
  },
  poweredByAndVersion: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    rowGap: theme.spacing(0.5)
  }
}));

const initialValues: LoginFormValues = {
  alias: '',
  password: ''
};

const LoginPage = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const validationSchema = useValidationSchema();

  const {
    submitLoginForm,
    providersConfiguration,
    loginPageCustomisation,
    authenticationError,
    hasForcedProvider
  } = useLogin();

  const areUserParametersLoaded = useAtomValue(areUserParametersLoadedAtom);
  const platformVersions = useAtomValue(platformVersionsAtom);

  if (areUserParametersLoaded || isNil(areUserParametersLoaded)) {
    return <MainLoaderWithoutTranslation />;
  }

  if (authenticationError && hasForcedProvider) {
    return (
      <FallbackPage
        message={authenticationError}
        title={t(labelAnErrorOccurredDuringAuthentication)}
      />
    );
  }

  const hasProvidersConfiguration = !isNil(providersConfiguration);

  return (
    <div>
      <Suspense fallback={<LoadingSkeleton />}>
        <WallpaperPage
          wallpaperAlt={t(labelCentreonWallpaper)}
          wallpaperSource={loginPageCustomisation.imageSource}
        >
          <>
            <Suspense fallback={<LoadingSkeleton height={50} width={250} />}>
              <LoginHeader loginPageCustomisation={loginPageCustomisation} />
            </Suspense>
            {equals(loginPageCustomisation.textPosition, 'top') && (
              <CustomText loginPageCustomisation={loginPageCustomisation} />
            )}
            <Typography variant="h5">{t(labelLogin)}</Typography>
            <div>
              <Formik<LoginFormValues>
                validateOnMount
                initialValues={initialValues}
                validationSchema={validationSchema}
                onSubmit={submitLoginForm}
              >
                <Suspense
                  fallback={
                    <LoadingSkeleton height={45} variant="text" width={250} />
                  }
                >
                  <LoginForm />
                </Suspense>
              </Formik>
              {hasProvidersConfiguration && (
                <Suspense
                  fallback={
                    <LoadingSkeleton height={45} variant="text" width={250} />
                  }
                >
                  <ExternalProviders
                    providersConfiguration={providersConfiguration}
                  />
                </Suspense>
              )}
            </div>
            {equals(loginPageCustomisation.textPosition, 'bottom') && (
              <CustomText loginPageCustomisation={loginPageCustomisation} />
            )}
            <div className={classes.poweredByAndVersion}>
              <Typography variant="body2">
                {t(labelPoweredByCentreon)}
              </Typography>
              {isNil(platformVersions) ? (
                <LoadingSkeleton variant="text" width="40%" />
              ) : (
                <Typography variant="body2">
                  v. {platformVersions?.web.version}
                </Typography>
              )}
            </div>
          </>
        </WallpaperPage>
      </Suspense>
    </div>
  );
};

export default memo(LoginPage, T);
