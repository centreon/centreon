import { useCallback, useState } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { isEmpty, not, prop } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import LockIcon from '@mui/icons-material/Lock';
import PersonIcon from '@mui/icons-material/Person';
import { Button, CircularProgress } from '@mui/material';

import { TextField } from '@centreon/ui';

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
  const passwordValue = prop(passwordFieldName, values);
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
        fullWidth
        required
        StartAdornment={PersonIcon}
        ariaLabel={labelAlias}
        error={aliasError}
        label={t(labelAlias)}
        value={aliasValue || ''}
        onBlur={handleBlur(aliasFieldName)}
        onChange={handleChange(aliasFieldName)}
      />
      <TextField
        fullWidth
        required
        EndAdornment={passwordEndAdornment}
        StartAdornment={LockIcon}
        ariaLabel={labelPassword}
        error={passwordError}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              'aria-label': t(labelPassword) as string,
              autoComplete: 'new-password'
            }
          }
        }}
        label={t(labelPassword)}
        type={isVisible ? 'text' : 'password'}
        value={passwordValue || ''}
        onBlur={handleBlur(passwordFieldName)}
        onChange={handleChange(passwordFieldName)}
      />
      <Button
        fullWidth
        aria-label={labelConnect}
        color="primary"
        disabled={isDisabled}
        endIcon={isSubmitting && <CircularProgress color="inherit" size={20} />}
        type="submit"
        variant="contained"
      >
        {t(labelConnect)}
      </Button>
    </form>
  );
};

export default LoginForm;
