import { Modal } from '@centreon/ui/components';

import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { useCanEditProperties } from '../hooks/useCanEditDashboard';
import { labelCancel, labelSave } from '../translatedLabels';

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
      disabled={isDisabled}
      isFixed
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
