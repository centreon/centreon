import { Form } from '@centreon/ui';
import { FormActions, Modal } from '@centreon/ui/components';

import { type JSX, useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { labelCancel, labelDuplicate } from '../../translatedLabels';
import useDuplicateWithName, {
  type DuplicateWithNameValues
} from './useDuplicateWithName';
import useFormInputs from './useFormInputs';
import useValidationSchema from './useValidationSchema';

const DuplicateWithNameDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { close, headerContent, initialValues, isOpened, submit } =
    useDuplicateWithName();
  const { inputs } = useFormInputs();
  const { validationSchema } = useValidationSchema();

  const Buttons = useCallback(
    () => (
      <FormActions<DuplicateWithNameValues>
        enableSubmitWhenNotDirty
        labels={{
          cancel: t(labelCancel),
          submit: { create: t(labelDuplicate), update: t(labelDuplicate) }
        }}
        onCancel={close}
        variant="create"
      />
    ),
    [close, t]
  );

  return (
    <Modal onClose={close} open={isOpened}>
      <Modal.Header>{headerContent}</Modal.Header>
      <Modal.Body>
        <Form<DuplicateWithNameValues>
          Buttons={Buttons}
          initialValues={initialValues}
          inputs={inputs}
          submit={submit}
          validationSchema={validationSchema}
        />
      </Modal.Body>
    </Modal>
  );
};

export default DuplicateWithNameDialog;
