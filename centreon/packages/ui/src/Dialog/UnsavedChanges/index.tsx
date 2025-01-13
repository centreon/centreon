import { not } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Modal } from '../../components/Modal';

import {
  labelChangesWillNotBeSaved,
  labelDiscard,
  labelDoYouWantToLeaveThisPage,
  labelDoYouWantToSaveChanges,
  labelIfYouClickOnDiscard,
  labelReturn,
  labelSave
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
    : labelDoYouWantToLeaveThisPage;

  const labelConfirm = isValidForm ? labelSave : labelReturn;

  const labelMessage = isValidForm
    ? labelIfYouClickOnDiscard
    : labelChangesWillNotBeSaved;

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
          cancel: t(labelDiscard),
          confirm: t(labelConfirm)
        }}
        onCancel={discardChanges}
        onConfirm={isValidForm ? saveChanges : closeDialog}
      />
    </Modal>
  );
};

export default UnsavedChangesDialog;
