import { UnsavedChangesDialog } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useAtom, useSetAtom } from 'jotai';
import { useCallback } from 'react';
import { useSearchParams } from 'react-router';

import { formStateAtom, isCloseConfirmationDialogOpenAtom } from '../atoms';

const CloseModal = (): JSX.Element => {
  const { isValid, dirty, isSubmitting, submitForm } = useFormikContext();

  const [, setSearchParams] = useSearchParams();
  const [isDialogOpen, setIsDialogOpen] = useAtom(
    isCloseConfirmationDialogOpenAtom
  );
  const setFormState = useSetAtom(formStateAtom);

  const discard = useCallback(() => {
    setIsDialogOpen(false);
    setSearchParams({});

    setFormState((formState) => ({ ...formState, isOpen: false }));
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
      closeDialog={closeDialog}
      dialogOpened={isDialogOpen && dirty}
      discardChanges={discard}
      isSubmitting={isSubmitting}
      isValidForm={isValid}
      saveChanges={submitAndClose}
    />
  );
};

export default CloseModal;
