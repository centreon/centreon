import { useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useCanEditProperties } from '../hooks/useCanEditDashboard';
import { labelCancel, labelSave } from '../translatedLabels';
import { widgetFormInitialDataAtom } from './atoms';

interface Props {
  closeModal: (shouldAskForClosingConfirmation: boolean) => void;
}

const Actions = ({ closeModal }: Props): JSX.Element | null => {
  const { t } = useTranslation();

  const widgetFormInitialData = useAtomValue(widgetFormInitialDataAtom);

  const { handleSubmit, isValid, dirty, isSubmitting, values } =
    useFormikContext();

  const { canEdit, canEditField } = useCanEditProperties();

  if (!canEdit || !canEditField) {
    return null;
  }
  console.log(
    equals(values, widgetFormInitialData),
    values,
    widgetFormInitialData
  );

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
