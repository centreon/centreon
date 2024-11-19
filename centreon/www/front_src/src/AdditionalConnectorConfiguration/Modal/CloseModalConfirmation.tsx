import { useFormikContext } from 'formik';
import { useAtom, useSetAtom } from 'jotai';

import { UnsavedChangesDialog } from '@centreon/ui';
import { useCallback } from 'react';
import { dialogStateAtom, isCloseModalDialogOpenAtom } from '../atoms';

const CloseModalConfirmation = (): JSX.Element => {
  const { isValid, dirty, isSubmitting, submitForm } = useFormikContext();

  const [isModalOpen, setIsModalOpen] = useAtom(isCloseModalDialogOpenAtom);
  const setDialogState = useSetAtom(dialogStateAtom);

  const discard = useCallback(() => {
    setIsModalOpen(false);
    setDialogState((dialogState) => ({ ...dialogState, isOpen: false }));
  }, []);

  const submitAndClose = useCallback(() => {
    submitForm();
    setIsModalOpen(false);
  }, []);

  const closeAskBeforeCloseModal = useCallback(() => {
    setIsModalOpen(false);
  }, []);

  return (
    <UnsavedChangesDialog
      isSubmitting={isSubmitting}
      isValidForm={isValid}
      saveChanges={submitAndClose}
      closeDialog={closeAskBeforeCloseModal}
      discardChanges={discard}
      dialogOpened={isModalOpen && dirty}
    />
  );
};

export default CloseModalConfirmation;
