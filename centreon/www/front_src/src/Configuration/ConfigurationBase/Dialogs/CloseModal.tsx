import { UnsavedChangesDialog } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useCallback } from 'react';
import { useSearchParams } from 'react-router';

import {
  formStateAtom,
  formStateOnDiscardAtom,
  isCloseConfirmationDialogOpenAtom,
  isMassChangeConfirmationDialogOpenAtom
} from '../atoms';

const CloseModal = (): JSX.Element => {
  const { isValid, dirty, isSubmitting, submitForm } = useFormikContext();

  const [, setSearchParams] = useSearchParams();
  const [isDialogOpen, setIsDialogOpen] = useAtom(
    isCloseConfirmationDialogOpenAtom
  );
  const setFormState = useSetAtom(formStateAtom);
  const { mode } = useAtomValue(formStateAtom);
  const [formStateOnDiscard, setFormStateOnDiscard] = useAtom(
    formStateOnDiscardAtom
  );
  const setIsMassChangeConfirmationDialogOpen = useSetAtom(
    isMassChangeConfirmationDialogOpenAtom
  );

  const discard = useCallback(() => {
    setIsDialogOpen(false);
    setFormStateOnDiscard(null);

    if (formStateOnDiscard) {
      setSearchParams({
        id: String(formStateOnDiscard.id),
        mode: formStateOnDiscard.mode
      });
      setFormState(formStateOnDiscard);

      return;
    }

    setSearchParams({});

    setFormState((formState) => ({ ...formState, isOpen: false }));
  }, [
    formStateOnDiscard,
    setFormState,
    setFormStateOnDiscard,
    setIsDialogOpen,
    setSearchParams
  ]);

  const submitAndClose = useCallback(() => {
    setFormStateOnDiscard(null);

    // A mass change is written only once its count is confirmed, from here
    // too.
    if (equals(mode, 'massChange')) {
      setIsDialogOpen(false);
      setIsMassChangeConfirmationDialogOpen(true);

      return;
    }

    submitForm().then(() => {
      setIsDialogOpen(false);
      setSearchParams({});
    });
  }, [
    mode,
    setFormStateOnDiscard,
    setIsDialogOpen,
    setIsMassChangeConfirmationDialogOpen,
    setSearchParams,
    submitForm
  ]);

  const closeDialog = useCallback(() => {
    setIsDialogOpen(false);
    setFormStateOnDiscard(null);
  }, [setFormStateOnDiscard, setIsDialogOpen]);

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
