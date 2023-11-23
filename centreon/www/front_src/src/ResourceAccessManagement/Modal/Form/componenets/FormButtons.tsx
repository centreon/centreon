import { ReactElement } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';

import { Button } from '@mui/material';

import { labelExit, labelSave } from '../../../translatedLabels';
import { modalStateAtom } from '../../atom';

import useStyles from './FormButtons.styles';

const FormButtons = (): ReactElement => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { isSubmitting, submitForm } = useFormikContext<FormikValues>();

  const [modalState, setModalState] = useAtom(modalStateAtom);

  const closeModal = (): void => {
    setModalState({ ...modalState, isOpen: false });
  };

  return (
    <div className={classes.buttons}>
      <Button
        aria-label={t(labelExit)}
        disabled={isSubmitting}
        size="small"
        variant="text"
        onClick={closeModal}
      >
        {t(labelExit)}
      </Button>
      <Button
        aria-label={t(labelSave)}
        disabled={isSubmitting}
        size="small"
        variant="contained"
        onClick={submitForm}
      >
        {t(labelSave)}
      </Button>
    </div>
  );
};

export default FormButtons;
