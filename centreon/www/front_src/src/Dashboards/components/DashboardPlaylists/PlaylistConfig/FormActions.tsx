import { useAtom, useSetAtom } from 'jotai';
import { useFormikContext, FormikValues } from 'formik';
import { useTranslation } from 'react-i18next';

import { UnsavedChangesDialog } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';

import {
  askBeforeClosePlaylistConfigAtom,
  playlistConfigInitialValuesAtom
} from '../atoms';
import { labelCancel } from '../../../translatedLabels';
import { labelSave } from '../../../Dashboard/translatedLabels';

const FormActions = (): JSX.Element => {
  const { t } = useTranslation();

  const { isSubmitting, isValid, dirty, submitForm } =
    useFormikContext<FormikValues>();

  const [askBeforeClosePlaylistConfig, setAskBeforeClosePlaylistConfig] =
    useAtom(askBeforeClosePlaylistConfigAtom);
  const setPlaylistConfigInitialValues = useSetAtom(
    playlistConfigInitialValuesAtom
  );

  const isValidForm = isValid && dirty;
  const isDisabled = !dirty || !isValid;

  const closeUnsavedChanges = (): void => {
    setAskBeforeClosePlaylistConfig(false);
  };

  const discardChanges = (): void => {
    setAskBeforeClosePlaylistConfig(false);
    setPlaylistConfigInitialValues(null);
  };

  return (
    <div>
      <Modal.Actions
        disabled={isDisabled}
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelSave)
        }}
        onCancel={dirty ? askBeforeClosePlaylistConfig : discardChanges}
        onConfirm={submitForm}
      />
      <UnsavedChangesDialog
        closeDialog={closeUnsavedChanges}
        dialogOpened={askBeforeClosePlaylistConfig}
        discardChanges={discardChanges}
        isSubmitting={isSubmitting}
        isValidForm={isValidForm}
        saveChanges={submitForm}
      />
    </div>
  );
};

export default FormActions;
