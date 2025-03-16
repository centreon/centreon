import { Modal } from '@centreon/ui/components';

import { Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';
import { labelCreateAuthenticationToken } from '../translatedLabels';
import Form from './Form/Form';
import useModal from './useModal';

import { useStyles } from './Modal.styles';

const FormModal = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { close, isOpen } = useModal();

  return (
    <Modal data-testid="Modal" open={isOpen} size="medium" onClose={close}>
      <Modal.Header data-testid="Modal-header">
        <Typography className={classes.modalHeader}>
          {t(labelCreateAuthenticationToken)}
        </Typography>
      </Modal.Header>
      <Modal.Body>
        <Form close={close} />
      </Modal.Body>
    </Modal>
  );
};

export default FormModal;
