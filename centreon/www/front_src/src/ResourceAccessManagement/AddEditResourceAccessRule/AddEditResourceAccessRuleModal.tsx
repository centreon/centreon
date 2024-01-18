import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import {
  labelCreateResourceAccessRule,
  labelEditResourceAccessRule
} from '../translatedLabels';

import useResourceAccessRuleModal from './useResourceAccessRuleModal';
import useModalStyles from './Modal.styles';
import { Form } from './Form';

const AddEditResourceAccessRuleModal = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useModalStyles();

  const { closeModal, isModalOpen, mode } = useResourceAccessRuleModal();

  const labels = useMemo(
    (): {
      modalTitle: {
        create: string;
        edit: string;
      };
    } => ({
      modalTitle: {
        create: t(labelCreateResourceAccessRule),
        edit: t(labelEditResourceAccessRule)
      }
    }),
    []
  );

  return (
    <Modal open={isModalOpen} size="xlarge" onClose={closeModal}>
      <Modal.Header>
        <Typography className={classes.modalTitle}>
          {labels.modalTitle[mode]}
        </Typography>
      </Modal.Header>
      <Modal.Body>
        <Form />
      </Modal.Body>
    </Modal>
  );
};

export default AddEditResourceAccessRuleModal;
