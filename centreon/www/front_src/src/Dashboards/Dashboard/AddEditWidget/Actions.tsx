import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { Modal } from '@centreon/ui/components';

import { labelExit, labelSave } from '../translatedLabels';

interface Props {
  closeModal: (shouldAskForClosingConfirmation: boolean) => void;
}

const Actions = ({ closeModal }: Props): JSX.Element => {
  const { t } = useTranslation();

  const { handleSubmit, isValid, dirty } = useFormikContext();

  const isDisabled = !dirty || !isValid;

  return (
    <Modal.Actions
      disabled={isDisabled}
      labels={{
        cancel: t(labelExit),
        confirm: t(labelSave)
      }}
      onCancel={() => closeModal(dirty)}
      onConfirm={handleSubmit}
    />
  );
};

export default Actions;
