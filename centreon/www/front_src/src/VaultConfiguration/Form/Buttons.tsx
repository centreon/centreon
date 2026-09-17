import SaveIcon from '@mui/icons-material/Save';
import { CircularProgress } from '@mui/material';

import { Button, Modal } from '@centreon/ui/components';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { canMigrateAtom } from '../atoms';
import {
  labelCancel,
  labelFormWillBeCleared,
  labelMigrate,
  labelReset,
  labelResetConfiguration,
  labelSave
} from '../translatedLabels';
import MigrationModal from './MigrationModal';
import { useFormStyles } from './useFormStyles';

const Buttons = (): JSX.Element => {
  const { classes } = useFormStyles();
  const { t } = useTranslation();

  const [isResetModalOpen, setIsResetModalOpen] = useState(false);
  const [isMigrationModalOpen, setIsMigrationModalOpen] = useState(false);

  const canMigrate = useAtomValue(canMigrateAtom);

  const { isValid, dirty, isSubmitting, resetForm, submitForm } =
    useFormikContext();

  const isSubmitDisabled = useMemo(
    () => !dirty || !isValid || isSubmitting,
    [dirty, isValid, isSubmitting]
  );

  const isResetDisabled = useMemo(() => !dirty, [dirty]);

  const openResetModal = useCallback(() => setIsResetModalOpen(true), []);

  const closeResetModal = useCallback(() => setIsResetModalOpen(false), []);

  const openMigrationModal = useCallback(
    () => setIsMigrationModalOpen(true),
    []
  );

  const closeMigrationModal = useCallback(
    () => setIsMigrationModalOpen(false),
    []
  );

  const closeAndReset = (): void => {
    resetForm();
    closeResetModal();
  };

  return (
    <div className={classes.buttons}>
      <Button
        disabled={!canMigrate}
        onClick={openMigrationModal}
        variant="ghost"
      >
        {t(labelMigrate)}
      </Button>
      <div>
        {isSubmitting && <CircularProgress size={24} />}
        <Button
          disabled={isResetDisabled}
          onClick={openResetModal}
          variant="ghost"
        >
          {t(labelReset)}
        </Button>
        <Button
          disabled={isSubmitDisabled}
          icon={<SaveIcon />}
          iconVariant="start"
          onClick={submitForm}
        >
          {t(labelSave)}
        </Button>
      </div>

      <Modal onClose={closeResetModal} open={isResetModalOpen}>
        <Modal.Header>{t(labelResetConfiguration)}</Modal.Header>
        <Modal.Body>{t(labelFormWillBeCleared)}</Modal.Body>
        <Modal.Actions
          labels={{
            cancel: t(labelCancel),
            confirm: t(labelReset)
          }}
          onCancel={closeResetModal}
          onConfirm={closeAndReset}
        />
      </Modal>
      <MigrationModal
        close={closeMigrationModal}
        isOpen={isMigrationModalOpen}
      />
    </div>
  );
};

export default Buttons;
