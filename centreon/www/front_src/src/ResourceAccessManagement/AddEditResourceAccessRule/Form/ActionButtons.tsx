import { ReactElement } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { useAtom, useSetAtom } from 'jotai';
import { or } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Button, CircularProgress } from '@mui/material';

import {
  isCloseModalConfirmationDialogOpenAtom,
  isDirtyAtom,
  modalStateAtom
} from '../../atom';
import { ModalMode } from '../../models';
import { labelExit, labelSave } from '../../translatedLabels';

import { useActionButtonsStyles } from './Form.styles';

const ActionButtons = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useActionButtonsStyles();
  const exitDataTestId = 'exitForm';
  const submitDataTestId = 'submitForm';

  const [isDirty, setIsDirty] = useAtom(isDirtyAtom);
  const setModalState = useSetAtom(modalStateAtom);
  const setIsDialogOpen = useSetAtom(isCloseModalConfirmationDialogOpenAtom);

  const { isSubmitting, isValid, dirty, submitForm } =
    useFormikContext<FormikValues>();

  setIsDirty(dirty);

  const close = (): void =>
    setModalState({ isOpen: false, mode: ModalMode.Create });

  const askBeforeClose = (): void => {
    if (isDirty) {
      setIsDialogOpen(true);

      return;
    }

    setIsDirty(false);
    close();
  };

  return (
    <div className={classes.buttonContainer}>
      <Button
        aria-label={labelExit}
        data-testid={exitDataTestId as string}
        variant="text"
        onClick={askBeforeClose}
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
