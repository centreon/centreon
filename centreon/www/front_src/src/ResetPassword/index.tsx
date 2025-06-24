import { Suspense, useEffect } from 'react';

import { Formik } from 'formik';
import { useAtomValue } from 'jotai';
import { isNil, not } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

import { CentreonLogo, LoadingSkeleton, WallpaperPage } from '@centreon/ui';

import {
  labelCentreonWallpaper,
  labelPoweredByCentreon
} from '../Login/translatedLabels';
import useWallpaper from '../Login/useWallpaper';
import { MainLoaderWithoutTranslation } from '../Main/MainLoader';
import { platformVersionsAtom } from '../Main/atoms/platformVersionsAtom';
import routeMap from '../reactRoutes/routeMap';

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

  const { submitResetPassword, validationSchema } = useResetPassword();

  const wallpaper = useWallpaper();

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
          wallpaperSource={wallpaper}
        >
          <>
            <CentreonLogo />
            <div className={classes.form}>
              <Typography variant="h5">{t(labelResetYourPassword)}</Typography>
              <Formik<ResetPasswordValues>
                initialValues={initialValues}
                validationSchema={validationSchema}
                onSubmit={submitResetPassword}
              >
                <Form />
              </Formik>
            </div>
            <div className={classes.poweredByAndVersion}>
              <Typography variant="body2">
                {t(labelPoweredByCentreon)}
              </Typography>
              {isNil(platformVersions) ? (
                <LoadingSkeleton variant="text" width="40%" />
              ) : (
                <Typography variant="body2">
                  v. {platformVersions?.web?.version}
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
