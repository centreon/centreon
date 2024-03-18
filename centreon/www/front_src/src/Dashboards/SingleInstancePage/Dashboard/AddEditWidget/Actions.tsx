import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { Modal } from '@centreon/ui/components';

import { labelCancel, labelSave } from '../translatedLabels';
import { useCanEditProperties } from '../hooks/useCanEditDashboard';

interface Props {
  closeModal: (shouldAskForClosingConfirmation: boolean) => void;
}

const Actions = ({ closeModal }: Props): JSX.Element | null => {
  const { t } = useTranslation();

  const { handleSubmit, isValid, dirty, isSubmitting } = useFormikContext();

  const { canEdit, canEditField } = useCanEditProperties();

  if (!canEdit || !canEditField) {
    return null;
  }

  const isDisabled = isSubmitting || !dirty || !isValid;

  return (
    <Modal.Actions
      isFixed
      disabled={isDisabled}
      labels={{
        cancel: t(labelCancel),
        confirm: t(labelSave)
      }}
      onCancel={() => closeModal(dirty)}
      onConfirm={handleSubmit}
    />
  );
};

export default Actions;
