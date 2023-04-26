import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { isNil, not } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Theme, LinearProgress } from '@mui/material';

import useTab from '../useTab';
import FormTitle from '../FormTitle';

import { labelDefinePasswordPasswordSecurityPolicy } from './translatedLabels';
import useAuthentication from './useAuthentication';
import Form from './Form';
import { PasswordSecurityPolicy } from './models';

const useStyles = makeStyles()((theme: Theme) => ({
  loading: {
    height: theme.spacing(0.5)
  }
}));

const LocalAuthentication = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const {
    sendingGetPasswordPasswordSecurityPolicy,
    initialPasswordPasswordSecurityPolicy,
    loadPasswordPasswordSecurityPolicy
  } = useAuthentication();

  const isPasswordSecurityPolicyEmpty = useMemo(
    () => isNil(initialPasswordPasswordSecurityPolicy),
    [initialPasswordPasswordSecurityPolicy]
  );

  useTab(isPasswordSecurityPolicyEmpty);

  return (
    <div>
      <FormTitle title={t(labelDefinePasswordPasswordSecurityPolicy)} />
      <div className={classes.loading}>
        {not(isPasswordSecurityPolicyEmpty) &&
          sendingGetPasswordPasswordSecurityPolicy && <LinearProgress />}
      </div>
      <Form
        initialValues={
          initialPasswordPasswordSecurityPolicy as PasswordSecurityPolicy
        }
        isLoading={isPasswordSecurityPolicyEmpty}
        loadPasswordSecurityPolicy={loadPasswordPasswordSecurityPolicy}
      />
    </div>
  );
};

export default LocalAuthentication;
