import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { Modal } from '@centreon/ui/components';

import { labelCancel } from '../../translatedLabels';
import { labelAdd, labelEdit } from '../translatedLabels';

interface Props {
  closeModal: () => void;
  isAddingWidget: boolean;
}

const Actions = ({ isAddingWidget, closeModal }: Props): JSX.Element => {
  const { t } = useTranslation();

  const { handleSubmit, isValid, dirty } = useFormikContext();

  const isDisabled = !dirty || !isValid;

  return (
    <Modal.Actions
      disabled={isDisabled}
      labels={{
        cancel: t(labelCancel),
        confirm: t(isAddingWidget ? labelAdd : labelEdit)
      }}
      onCancel={closeModal}
      onConfirm={handleSubmit}
    />
  );
};

export default Actions;
