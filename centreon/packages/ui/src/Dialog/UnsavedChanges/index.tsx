import { not } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Modal } from '../../components/Modal';

import {
  labelDiscard,
  labelDoYouWantToQuitWithoutResolving,
  labelDoYouWantToResolveErrors,
  labelDoYouWantToSaveChanges,
  labelIfYouClickOnDiscard,
  labelResolve,
  labelSave,
  labelThereAreErrorsInTheForm
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
    : labelDoYouWantToResolveErrors;

  const labelConfirm = isValidForm ? labelSave : labelResolve;

  const labelMessage = `${
    isValidForm ? labelIfYouClickOnDiscard : labelThereAreErrorsInTheForm
  }. ${isValidForm ? '' : labelDoYouWantToQuitWithoutResolving}`;

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
