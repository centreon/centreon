import { ReactElement } from 'react';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { FormikValues, useFormikContext } from 'formik';
import { or } from 'ramda';

import { Button, CircularProgress } from '@mui/material';

import { labelExit, labelSave } from '../../translatedLabels';
import { modalStateAtom } from '../../atom';
import { ModalMode } from '../../models';

import { useActionButtonsStyles } from './Form.styles';

const ActionButtons = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useActionButtonsStyles();
  const exitDataTestId = 'exitForm';
  const submitDataTestId = 'submitForm';

  const setModalState = useSetAtom(modalStateAtom);

  const { isSubmitting, isValid, dirty, submitForm } =
    useFormikContext<FormikValues>();

  const close = (): void =>
    setModalState({ isOpen: false, mode: ModalMode.Create });

  return (
    <div className={classes.buttonContainer}>
      <Button
        aria-label={labelExit}
        data-testid={exitDataTestId as string}
        variant="text"
        onClick={close}
      >
        {t(labelExit)}
      </Button>
      {isSubmitting ? (
        <CircularProgress color="primary" size={20} />
      ) : (
        <Button
          aria-label={labelSave}
          data-testid={submitDataTestId as string}
          disabled={or(!isValid, !dirty) as boolean}
          variant="contained"
          onClick={submitForm}
        >
          {t(labelSave)}
        </Button>
      )}
    </div>
  );
};

export default ActionButtons;
