import { UnsavedChangesDialog } from '@centreon/ui';
import { useTranslation } from 'react-i18next';

import { useFormikContext } from 'formik';
import { useAtom } from 'jotai';

import { ReactElement, useCallback, useEffect } from 'react';

import { equals } from 'ramda';
import { FormActions } from '../../Form';
import { askBeforeCloseFormModalAtom, openFormModalAtom } from '../atoms';
import { labelCancel, labelSave } from '../translatedLabels';

const Buttons = (): ReactElement => {
  const { t } = useTranslation();

  const [askBeforeCloseForm, setAskBeforeCloseFormModal] = useAtom(
    askBeforeCloseFormModalAtom
  );
  const [openFormModal, setOpenFormModal] = useAtom(openFormModalAtom);

  const { isValid, dirty, isSubmitting, submitForm } = useFormikContext();

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

  const actionsLabels = {
    cancel: t(labelCancel),
    submit: {
      create: t(labelSave),
      update: t(labelSave)
    }
  };

  const variant = equals(openFormModal, 'add') ? 'create' : 'update';

  return (
    <>
      <FormActions labels={actionsLabels} variant={variant} onCancel={close} />
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
