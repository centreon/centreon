import { Modal } from '@centreon/ui/components';

import { JSX } from 'react';

import { Form as FormType } from '../../models';
import Form from './Form/Form';
import useModal from './useModal';

interface Props {
  form: FormType;
  hasWriteAccess: boolean;
}

const FormModal = ({ form, hasWriteAccess }: Props): JSX.Element => {
  const {
    labelHeader,
    submit,
    close,
    isOpen,
    mode,
    id,
    initialValues,
    isLoading
  } = useModal({ defaultValues: form.defaultValues, hasWriteAccess });

  return (
    <Modal data-testid="Modal" onClose={close} open={isOpen} size="xlarge">
      <Modal.Header data-testid="Modal-header">{labelHeader}</Modal.Header>
      <Modal.Body>
        <Form
          groups={form?.groups}
          hasWriteAccess={hasWriteAccess}
          id={id}
          initialValues={initialValues}
          inputs={form?.inputs}
          isLoading={isLoading}
          mode={mode}
          onCancel={close}
          onSubmit={submit}
          validationSchema={form?.validationSchema}
        />
      </Modal.Body>
    </Modal>
  );
};

export default FormModal;
