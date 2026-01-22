import LockIcon from '@mui/icons-material/Lock';
import PersonIcon from '@mui/icons-material/Person';
import { Button, CircularProgress } from '@mui/material';

import { TextField } from '@centreon/ui';

import { FormikValues, useFormikContext } from 'formik';
import { isEmpty, not, prop } from 'ramda';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import PasswordEndAdornment from './PasswordEndAdornment';
import { labelAlias, labelConnect, labelPassword } from './translatedLabels';

const aliasFieldName = 'alias';
const passwordFieldName = 'password';

const useStyles = makeStyles()((theme) => ({
  form: {
    display: 'flex',
    flexDirection: 'column',
    rowGap: theme.spacing(2),
    width: '100%'
  }
}));

const getTouchedError = ({ fieldName, errors, touched }): string | undefined =>
  prop(fieldName, touched) && prop(fieldName, errors);

const LoginForm = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [isVisible, setIsVisible] = useState(false);
  const {
    values,
    handleChange,
    errors,
    touched,
    handleBlur,
    dirty,
    isSubmitting,
    handleSubmit
  } = useFormikContext<FormikValues>();

  const changeVisibility = (): void => {
    setIsVisible((currentIsVisible) => !currentIsVisible);
  };

  const aliasValue = prop(aliasFieldName, values);
  const aliasError = getTouchedError({
    errors,
    fieldName: aliasFieldName,
    touched
  });

  const passwordError = getTouchedError({
    errors,
    fieldName: passwordFieldName,
    touched
  });
  const isDisabled = not(isEmpty(errors)) || isSubmitting || not(dirty);

  const passwordEndAdornment = useCallback(
    (): JSX.Element => (
      <PasswordEndAdornment
        changeVisibility={changeVisibility}
        isVisible={isVisible}
      />
    ),
    [isVisible]
  );

  return (
    <form className={classes.form} onSubmit={handleSubmit}>
      <TextField
        ariaLabel={labelAlias}
        autoComplete="on"
        dataTestId={labelAlias}
        error={aliasError}
        fullWidth
        label={t(labelAlias)}
        onBlur={handleBlur(aliasFieldName)}
        onChange={handleChange(aliasFieldName)}
        required
        StartAdornment={PersonIcon}
        value={aliasValue || ''}
      />
      <TextField
        ariaLabel={labelPassword}
        autoComplete="on"
        dataTestId={labelPassword}
        EndAdornment={passwordEndAdornment}
        error={passwordError}
        forceUncontrolled
        fullWidth
        label={t(labelPassword)}
        onBlur={handleBlur(passwordFieldName)}
        onChange={handleChange(passwordFieldName)}
        required
        StartAdornment={LockIcon}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              'aria-label': t(labelPassword) as string,
              autoComplete: 'current-password'
            }
          }
        }}
        type={isVisible ? 'text' : 'password'}
      />
      <Button
        aria-label={labelConnect}
        color="primary"
        disabled={isDisabled}
        endIcon={isSubmitting && <CircularProgress color="inherit" size={20} />}
        fullWidth
        type="submit"
        variant="contained"
      >
        {t(labelConnect)}
      </Button>
    </form>
  );
};

export default LoginForm;
