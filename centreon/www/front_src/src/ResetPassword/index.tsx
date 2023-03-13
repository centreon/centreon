import { useEffect } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai/utils';
import { isNil, not } from 'ramda';
import { Formik } from 'formik';
import { makeStyles } from 'tss-react/mui';

import { Paper, Typography } from '@mui/material';

import { CentreonLogo } from '@centreon/ui';

import routeMap from '../reactRoutes/routeMap';
import { MainLoaderWithoutTranslation } from '../Main/MainLoader';

import { passwordResetInformationsAtom } from './passwordResetInformationsAtom';
import { labelResetYourPassword } from './translatedLabels';
import { ResetPasswordValues } from './models';
import useResetPassword, { router } from './useResetPassword';
import Form from './Form';

const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    backgroundColor: theme.palette.background.paper,
    display: 'flex',
    flexDirection: 'column',
    height: '100vh',
    justifyContent: 'center',
    maxWidth: theme.spacing(60),
    rowGap: theme.spacing(2)
  },
  paper: {
    padding: theme.spacing(4, 3)
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
    <div className={classes.container}>
      <CentreonLogo />
      <Paper className={classes.paper}>
        <Typography variant="h4">{t(labelResetYourPassword)}</Typography>
        <Formik<ResetPasswordValues>
          initialValues={initialValues}
          validationSchema={validationSchema}
          onSubmit={submitResetPassword}
        >
          <Form />
        </Formik>
      </Paper>
    </div>
  );
};

export default ResetPassword;
