import { Suspense, useEffect } from 'react';

import { Formik } from 'formik';
import { useAtomValue } from 'jotai';
import { equals, isNil, not } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

import { LoadingSkeleton, WallpaperPage } from '@centreon/ui';

import { MainLoaderWithoutTranslation } from '../Main/MainLoader';
import routeMap from '../reactRoutes/routeMap';

import { platformVersionsAtom } from '@centreon/ui-context';
import CustomText from '../Login/CustomText';
import LoginHeader from '../Login/LoginHeader';
import {
  labelCentreonWallpaper,
  labelPoweredByCentreon
} from '../Login/translatedLabels';
import useGetLoginCustomData from '../Login/useGetLoginCustomData';
import Form from './Form';
import { ResetPasswordValues } from './models';
import { passwordResetInformationsAtom } from './passwordResetInformationsAtom';
import { labelResetYourPassword } from './translatedLabels';
import useResetPassword, { router } from './useResetPassword';

const useStyles = makeStyles()((theme) => ({
  form: {
    width: '100%'
  },
  poweredByAndVersion: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    rowGap: theme.spacing(0.5)
  }
}));

const initialValues = {
  newPassword: '',
  newPasswordConfirmation: '',
  oldPassword: ''
};

const ResetPassword = (): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const navigate = router.useNavigate();

  const passwordResetInformations = useAtomValue(passwordResetInformationsAtom);
  const platformVersions = useAtomValue(platformVersionsAtom);

  const { loginPageCustomisation } = useGetLoginCustomData();

  const { submitResetPassword, validationSchema } = useResetPassword();

  useEffect(() => {
    if (
      not(isNil(passwordResetInformations)) &&
      passwordResetInformations?.alias
    ) {
      return;
    }

    navigate(routeMap.login);
  }, [passwordResetInformations]);

  if (
    isNil(passwordResetInformations) ||
    not(passwordResetInformations?.alias)
  ) {
    return <MainLoaderWithoutTranslation />;
  }

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
            <Typography variant="h5">{t(labelResetYourPassword)}</Typography>
            <div className={classes.form}>
              <Formik<ResetPasswordValues>
                initialValues={initialValues}
                validationSchema={validationSchema}
                onSubmit={submitResetPassword}
              >
                <Form />
              </Formik>
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

export default ResetPassword;
