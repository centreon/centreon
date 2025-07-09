import { Modal } from '@centreon/ui/components';

import { Typography } from '@mui/material';

import Form from './Form/Form';

import { Form as FormType } from '../../models';
import { useStyles } from './Modal.styles';
import useModal from './useModal';

interface Props {
  form: FormType;
  hasWriteAccess: boolean;
}

const FormModal = ({ form, hasWriteAccess }: Props): JSX.Element => {
  const { classes } = useStyles();

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
      <Modal.Header data-testid="Modal-header">
        <Typography className={classes.modalHeader}>{labelHeader}</Typography>
      </Modal.Header>
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
