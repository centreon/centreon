import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { Modal } from '@centreon/ui/components';

import { labelCancel, labelSave } from '../translatedLabels';
import { editProperties } from '../hooks/useCanEditDashboard';

interface Props {
  closeModal: (shouldAskForClosingConfirmation: boolean) => void;
}

const Actions = ({ closeModal }: Props): JSX.Element | null => {
  const { t } = useTranslation();

  const { handleSubmit, isValid, dirty } = useFormikContext();

  const { canEdit, canEditField } = editProperties.useCanEditProperties();

  if (!canEdit || !canEditField) {
    return null;
  }

  const isDisabled = !dirty || !isValid;

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
