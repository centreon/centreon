import { useFormikContext } from 'formik';
import { useAtom, useSetAtom } from 'jotai';

import { UnsavedChangesDialog } from '@centreon/ui';
import { useCallback } from 'react';
import { useSearchParams } from 'react-router';
import { isCloseConfirmationDialogOpenAtom, modalStateAtom } from '../atoms';

const CloseModal = (): JSX.Element => {
  const { isValid, dirty, isSubmitting, submitForm } = useFormikContext();

  const [, setSearchParams] = useSearchParams();
  const [isDialogOpen, setIsDialogOpen] = useAtom(
    isCloseConfirmationDialogOpenAtom
  );
  const setModalState = useSetAtom(modalStateAtom);

  const discard = useCallback(() => {
    setIsDialogOpen(false);
    setSearchParams({});

    setModalState((dialogState) => ({ ...dialogState, isOpen: false }));
  }, []);

  const submitAndClose = useCallback(() => {
    submitForm().then(() => {
      setIsDialogOpen(false);
      setSearchParams({});
    });
  }, []);

  const closeDialog = useCallback(() => {
    setIsDialogOpen(false);
  }, []);

  return (
    <UnsavedChangesDialog
      isSubmitting={isSubmitting}
      isValidForm={isValid}
      saveChanges={submitAndClose}
      closeDialog={closeDialog}
      discardChanges={discard}
      dialogOpened={isDialogOpen && dirty}
    />
  );
};

export default CloseModal;
