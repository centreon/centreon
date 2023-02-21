import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { FormikValues, useFormikContext } from 'formik';
import { not } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Button } from '@mui/material';

import SaveButton from '../Button/Save';
import useMemoComponent from '../utils/useMemoComponent';
import getNormalizedId from '../utils/getNormalizedId';

import {
  labelReset,
  labelSave,
  labelSaved,
  labelSaving
} from './translatedLabels';

const useStyles = makeStyles()((theme) => ({
  buttons: {
    alignItems: 'center',
    columnGap: theme.spacing(2),
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'flex-end',
    marginTop: theme.spacing(2)
  }
}));

const FormButtons = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [submitted, setSubmitted] = useState(false);

  const { isSubmitting, dirty, isValid, submitForm, resetForm } =
    useFormikContext<FormikValues>();

  const reset = (): void => {
    resetForm();
  };

  const submit = (): Promise<void> =>
    submitForm()
      .then(() => {
        setSubmitted(true);
        setTimeout(() => {
          setSubmitted(false);
        }, 700);
      })
      .catch(() => undefined);

  const canSubmit = not(isSubmitting) && dirty && isValid && not(submitted);
  const canReset = not(isSubmitting) && dirty && not(submitted);

  return useMemoComponent({
    Component: (
      <div className={classes.buttons}>
        <Button
          aria-label={t(labelReset) || ''}
          data-testid={labelReset}
          disabled={not(canReset)}
          id={getNormalizedId(labelReset)}
          size="small"
          onClick={reset}
        >
          {t(labelReset)}
        </Button>
        <SaveButton
          dataTestId={labelSave}
          disabled={not(canSubmit)}
          labelLoading={labelSaving}
          labelSave={labelSave}
          labelSucceeded={labelSaved}
          loading={isSubmitting}
          size="small"
          succeeded={submitted}
          onClick={submit}
        />
      </div>
    ),
    memoProps: [canSubmit, canReset, isSubmitting, submitted, classes]
  });
};

export default FormButtons;
