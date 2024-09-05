import { Button, Modal } from '@centreon/ui/components';
import SaveIcon from '@mui/icons-material/Save';
import { CircularProgress } from '@mui/material';
import { useFormikContext } from 'formik';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  labelCancel,
  labelFormWillBeCleared,
  labelReset,
  labelResetConfiguration,
  labelSave
} from '../translatedLabels';
import { useFormStyles } from './useFormStyles';

const Buttons = (): JSX.Element => {
  const { classes } = useFormStyles();
  const { t } = useTranslation();

  const [isResetModalOpen, setIsResetModalOpen] = useState(false);

  const { isValid, dirty, isSubmitting, resetForm, submitForm } =
    useFormikContext();

  const isSubmitDisabled = useMemo(
    () => !dirty || !isValid || isSubmitting,
    [dirty, isValid, isSubmitting]
  );

  const isResetDisabled = useMemo(() => !dirty, [dirty]);

  const openResetModal = useCallback(() => setIsResetModalOpen(true), []);

  const closeResetModal = useCallback(() => setIsResetModalOpen(false), []);

  const closeAndReset = (): void => {
    resetForm();
    closeResetModal();
  };

  return (
    <div className={classes.buttons}>
      {isSubmitting && <CircularProgress size={24} />}
      <Button
        variant="ghost"
        onClick={openResetModal}
        disabled={isResetDisabled}
      >
        {t(labelReset)}
      </Button>
      <Button
        disabled={isSubmitDisabled}
        iconVariant="start"
        icon={<SaveIcon />}
        onClick={submitForm}
      >
        {t(labelSave)}
      </Button>
      <Modal open={isResetModalOpen}>
        <Modal.Header>{t(labelResetConfiguration)}</Modal.Header>
        <Modal.Body>{t(labelFormWillBeCleared)}</Modal.Body>
        <Modal.Actions
          onCancel={closeResetModal}
          onConfirm={closeAndReset}
          labels={{
            cancel: t(labelCancel),
            confirm: t(labelReset)
          }}
        />
      </Modal>
    </div>
  );
};

export default Buttons;
