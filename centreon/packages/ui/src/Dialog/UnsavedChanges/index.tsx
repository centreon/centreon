import { not } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Modal } from '../../components/Modal';

import {
  labelDiscard,
  labelDoYouWantToQuit,
  labelDoYouWantToSaveChanges,
  labelIfYouClickOnDiscard,
  labelLeave,
  labelSave,
  labelStay,
  labelYourFormHasUnsavedChanges
} from './translatedLabels';

interface Props {
  closeDialog: () => void;
  dialogOpened: boolean;
  discardChanges: () => void;
  isSubmitting: boolean;
  isValidForm: boolean;
  saveChanges: () => void;
}

const UnsavedChangesDialog = ({
  isValidForm,
  isSubmitting,
  closeDialog,
  discardChanges,
  saveChanges,
  dialogOpened
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const labelTitle = isValidForm
    ? labelDoYouWantToSaveChanges
    : labelYourFormHasUnsavedChanges;

  const confirmLabel = isValidForm ? labelSave : labelLeave;
  const canelLabel = isValidForm ? labelDiscard : labelStay;

  const labelMessage = isValidForm
    ? labelIfYouClickOnDiscard
    : labelDoYouWantToQuit;

  if (not(dialogOpened)) {
    return null;
  }

  return (
    <Modal
      hasCloseButton
      open={dialogOpened}
      size="medium"
      onClose={closeDialog}
    >
      <Modal.Header>{t(labelTitle)}</Modal.Header>
      <Modal.Body>{t(labelMessage)}</Modal.Body>
      <Modal.Actions
        disabled={isSubmitting}
        labels={{
          cancel: t(canelLabel),
          confirm: t(confirmLabel)
        }}
        onCancel={closeDialog}
        onConfirm={isValidForm ? saveChanges : discardChanges}
      />
    </Modal>
  );
};

export default UnsavedChangesDialog;
