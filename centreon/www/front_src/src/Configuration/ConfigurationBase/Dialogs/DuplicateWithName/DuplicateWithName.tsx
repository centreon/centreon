import { Form, InputType } from '@centreon/ui';
import { FormActions, Modal } from '@centreon/ui/components';

import { type JSX, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { object, string } from 'yup';

import {
  labelCancel,
  labelDuplicate,
  labelName,
  labelRequired
} from '../../translatedLabels';
import useDuplicateWithName, {
  type DuplicateWithNameValues
} from './useDuplicateWithName';

const DuplicateWithNameDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { close, headerContent, initialValues, isOpened, submit } =
    useDuplicateWithName();

  const inputs = useMemo(
    () => [
      {
        autoFocus: true,
        dataTestId: labelName,
        fieldName: 'name',
        group: 'main',
        label: t(labelName),
        required: true,
        type: InputType.Text
      }
    ],
    [t]
  );

  const validationSchema = useMemo(
    () => object({ name: string().trim().required(t(labelRequired)) }),
    [t]
  );

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
