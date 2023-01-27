import { not } from 'ramda';
import { useTranslation } from 'react-i18next';

import { ConfirmDialog } from '../..';

import {
  labelDiscard,
  labelDoYouWantToQuitWithoutResolving,
  labelDoYouWantToQuitWithoutSaving,
  labelDoYouWantToResolveErrors,
  labelDoYouWantToSaveChanges,
  labelResolve,
  labelSave,
  labelThereAreErrorsInTheForm,
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
    : labelDoYouWantToResolveErrors;

  const labelConfirm = isValidForm ? labelSave : labelResolve;

  const labelMessage = `${
    isValidForm ? labelYourFormHasUnsavedChanges : labelThereAreErrorsInTheForm
  }. ${
    isValidForm
      ? labelDoYouWantToQuitWithoutSaving
      : labelDoYouWantToQuitWithoutResolving
  }`;

  if (not(dialogOpened)) {
    return null;
  }

  return (
    <ConfirmDialog
      confirmDisabled={isSubmitting}
      labelCancel={t(labelDiscard)}
      labelConfirm={t(labelConfirm)}
      labelMessage={t(labelMessage)}
      labelTitle={t(labelTitle)}
      open={dialogOpened}
      submitting={isSubmitting}
      onBackdropClick={closeDialog}
      onCancel={discardChanges}
      onConfirm={isValidForm ? saveChanges : closeDialog}
    />
  );
};

export default UnsavedChangesDialog;
