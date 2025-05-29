import { Modal } from '@centreon/ui/components';

import Form from './Form/Form';

import { JSX } from 'react';
import { Form as FormType } from '../../models';
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
    <Modal data-testid="Modal" open={isOpen} size="xlarge" onClose={close}>
      <Modal.Header data-testid="Modal-header">{labelHeader}</Modal.Header>
      <Modal.Body>
        <Form
          onSubmit={submit}
          onCancel={close}
          mode={mode}
          id={id}
          inputs={form?.inputs}
          groups={form?.groups}
          validationSchema={form?.validationSchema}
          initialValues={initialValues}
          isLoading={isLoading}
          hasWriteAccess={hasWriteAccess}
        />
      </Modal.Body>
    </Modal>
  );
};

export default FormModal;
