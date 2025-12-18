import SaveIcon from '@mui/icons-material/Save';
import { Box, CircularProgress } from '@mui/material';

import { UnsavedChangesDialog } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useAtom, useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useCallback, useEffect, useMemo } from 'react';

import { Button } from '../../Button';
import {
  askBeforeCloseFormModalAtom,
  formLabelButtonsAtom,
  openFormModalAtom
} from '../atoms';

const Buttons = (): JSX.Element => {
  const [askBeforeCloseForm, setAskBeforeCloseFormModal] = useAtom(
    askBeforeCloseFormModalAtom
  );
  const [openFormModal, setOpenFormModal] = useAtom(openFormModalAtom);
  const labels = useAtomValue(formLabelButtonsAtom);

  const { isValid, dirty, isSubmitting, submitForm } = useFormikContext();

  const isSubmitDisabled = useMemo(
    () => !dirty || !isValid || isSubmitting,
    [dirty, isValid, isSubmitting]
  );
  const cancelLabel = useMemo(
    () =>
      equals(openFormModal, 'add') ? labels.add.cancel : labels.update.cancel,
    [labels, openFormModal]
  );
  const confirmLabel = useMemo(
    () =>
      equals(openFormModal, 'add') ? labels.add.confirm : labels.update.confirm,
    [labels, openFormModal]
  );

  const discard = useCallback(() => {
    setAskBeforeCloseFormModal(false);
    setOpenFormModal(null);
  }, [setAskBeforeCloseFormModal, setOpenFormModal]);

  const close = useCallback(() => {
    if (dirty) {
      setAskBeforeCloseFormModal(true);
      return;
    }
    setOpenFormModal(null);
    setAskBeforeCloseFormModal(false);
  }, [dirty, setAskBeforeCloseFormModal, setOpenFormModal]);

  const submitAndClose = useCallback(() => {
    submitForm();
    setAskBeforeCloseFormModal(false);
  }, [setAskBeforeCloseFormModal, submitForm]);

  const closeAskBeforeCloseModal = useCallback(() => {
    setAskBeforeCloseFormModal(false);
  }, [setAskBeforeCloseFormModal]);

  useEffect(() => {
    if (!askBeforeCloseForm || dirty) {
      return;
    }

    close();
  }, [askBeforeCloseForm, dirty, close]);

  return (
    <>
      <Box sx={{ display: 'flex', gap: 2, justifyContent: 'flex-end' }}>
        {isSubmitting && <CircularProgress size={24} />}
        <Button onClick={close} variant="ghost">
          {cancelLabel}
        </Button>
        <Button
          disabled={isSubmitDisabled}
          icon={<SaveIcon />}
          iconVariant="start"
          onClick={submitForm}
        >
          {confirmLabel}
        </Button>
      </Box>
      <UnsavedChangesDialog
        closeDialog={closeAskBeforeCloseModal}
        dialogOpened={askBeforeCloseForm && dirty}
        discardChanges={discard}
        isSubmitting={isSubmitting}
        isValidForm={isValid}
        saveChanges={submitAndClose}
      />
    </>
  );
};

export default Buttons;
