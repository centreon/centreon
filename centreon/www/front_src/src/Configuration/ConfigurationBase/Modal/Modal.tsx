import { Modal } from '@centreon/ui/components';

import { Typography } from '@mui/material';

import Form from './Form/Form';

import { useStyles } from './Modal.styles';
import useModal from './useModal';

const FormModal = ({ form }): JSX.Element => {
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
  } = useModal({ defaultValues: form.defaultValues });

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
        />
      </Modal.Body>
    </Modal>
  );
};

export default FormModal;
