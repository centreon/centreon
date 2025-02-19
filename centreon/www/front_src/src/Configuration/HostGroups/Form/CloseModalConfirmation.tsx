import { useFormikContext } from 'formik';
import { useAtom, useSetAtom } from 'jotai';

import { UnsavedChangesDialog } from '@centreon/ui';
import { useCallback } from 'react';
import { modalStateAtom } from '../../ConfigurationBase/Modal/atoms'; // to be changed
import { isCloseConfirmationDialogOpenAtom } from '../atoms';

const CloseModalConfirmation = (): JSX.Element => {
  const { isValid, dirty, isSubmitting, submitForm } = useFormikContext();

  const [isDialogOpen, setIsDialogOpen] = useAtom(
    isCloseConfirmationDialogOpenAtom
  );
  const setModalState = useSetAtom(modalStateAtom);

  const discard = useCallback(() => {
    setIsDialogOpen(false);
    setModalState((dialogState) => ({ ...dialogState, isOpen: false }));
  }, []);

  const submitAndClose = useCallback(() => {
    submitForm();
    setIsDialogOpen(false);
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

export default CloseModalConfirmation;
