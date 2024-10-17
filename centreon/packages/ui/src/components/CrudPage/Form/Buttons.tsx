import { UnsavedChangesDialog } from '@centreon/ui';
import SaveIcon from '@mui/icons-material/Save';
import { Box, CircularProgress } from '@mui/material';
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
  }, []);

  const close = useCallback(() => {
    if (dirty) {
      setAskBeforeCloseFormModal(true);
      return;
    }
    setOpenFormModal(null);
    setAskBeforeCloseFormModal(false);
  }, [dirty]);

  const submitAndClose = useCallback(() => {
    submitForm();
    setAskBeforeCloseFormModal(false);
  }, []);

  const closeAskBeforeCloseModal = useCallback(() => {
    setAskBeforeCloseFormModal(false);
  }, []);

  useEffect(() => {
    if (!askBeforeCloseForm || dirty) {
      return;
    }

    close();
  }, [askBeforeCloseForm, dirty]);

  return (
    <>
      <Box sx={{ display: 'flex', gap: 2, justifyContent: 'flex-end' }}>
        {isSubmitting && <CircularProgress size={24} />}
        <Button variant="ghost" onClick={close}>
          {cancelLabel}
        </Button>
        <Button
          disabled={isSubmitDisabled}
          iconVariant="start"
          icon={<SaveIcon />}
          onClick={submitForm}
        >
          {confirmLabel}
        </Button>
      </Box>
      <UnsavedChangesDialog
        isSubmitting={isSubmitting}
        isValidForm={isValid}
        saveChanges={submitAndClose}
        closeDialog={closeAskBeforeCloseModal}
        discardChanges={discard}
        dialogOpened={askBeforeCloseForm && dirty}
      />
    </>
  );
};

export default Buttons;
