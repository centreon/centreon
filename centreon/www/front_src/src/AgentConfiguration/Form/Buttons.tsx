import { UnsavedChangesDialog } from '@centreon/ui';
import { Button } from '@centreon/ui/components';
import SaveIcon from '@mui/icons-material/Save';
import { Box, CircularProgress } from '@mui/material';
import { useFormikContext } from 'formik';
import { useAtom, useSetAtom } from 'jotai';
import { useCallback, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
  agentTypeFormAtom,
  askBeforeCloseFormModalAtom,
  openFormModalAtom
} from '../atoms';
import { labelCancel, labelSave } from '../translatedLabels';

const Buttons = (): JSX.Element => {
  const { t } = useTranslation();

  const [askBeforeCloseForm, setAskBeforeCloseFormModal] = useAtom(
    askBeforeCloseFormModalAtom
  );
  const setOpenFormModal = useSetAtom(openFormModalAtom);
  const setAgentTypeForm = useSetAtom(agentTypeFormAtom);

  const { isValid, dirty, isSubmitting, submitForm, errors } =
    useFormikContext();

  const isSubmitDisabled = useMemo(
    () => !dirty || !isValid || isSubmitting,
    [dirty, isValid, isSubmitting]
  );

  console.log(errors);

  const discard = useCallback(() => {
    setAskBeforeCloseFormModal(false);
    setOpenFormModal(null);
    setAgentTypeForm(null);
  }, []);

  const close = useCallback(() => {
    if (dirty) {
      setAskBeforeCloseFormModal(true);
      return;
    }
    setOpenFormModal(null);
    setAgentTypeForm(null);
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
          {t(labelCancel)}
        </Button>
        <Button
          disabled={isSubmitDisabled}
          iconVariant="start"
          icon={<SaveIcon />}
          onClick={submitForm}
        >
          {t(labelSave)}
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
