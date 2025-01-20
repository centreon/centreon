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
    : labelDoYouWantToQuit;

  const lebelConfirm = isValidForm ? labelSave : labelLeave;
  const labelCancel = isValidForm ? labelDiscard : labelStay;

  const labelMessage = isValidForm
    ? labelIfYouClickOnDiscard
    : labelYourFormHasUnsavedChanges;

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
          cancel: t(labelCancel),
          confirm: t(lebelConfirm)
        }}
        onCancel={isValidForm ? discardChanges : closeDialog}
        onConfirm={isValidForm ? saveChanges : discardChanges}
      />
    </Modal>
  );
};

export default UnsavedChangesDialog;
