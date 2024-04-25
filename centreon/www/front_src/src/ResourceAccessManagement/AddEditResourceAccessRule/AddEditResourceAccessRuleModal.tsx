import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { FormikValues, useFormikContext } from 'formik';
import { useSetAtom } from 'jotai';

import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import {
  labelCreateResourceAccessRule,
  labelEditResourceAccessRule
} from '../translatedLabels';
import { isCloseModalConfirmationDialogOpenAtom } from '../atom';

import useResourceAccessRuleModal from './useResourceAccessRuleModal';
import useModalStyles from './Modal.styles';
import { Form } from './Form';
import CloseModalConfirmationDialog from './CloseModalConfirmationDialog';

const AddEditResourceAccessRuleModal = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useModalStyles();

  const setIsDialogOpen = useSetAtom(isCloseModalConfirmationDialogOpenAtom);
  const { dirty } = useFormikContext<FormikValues>();

  const { closeModal, isModalOpen, mode } = useResourceAccessRuleModal();

  const askBeforeCloseModal = (): void => {
    if (dirty) {
      setIsDialogOpen(true);

      return;
    }
    closeModal();
  };

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
    <>
      <Modal open={isModalOpen} size="xlarge" onClose={askBeforeCloseModal}>
        <Modal.Header>
          <Typography className={classes.modalTitle}>
            {labels.modalTitle[mode]}
          </Typography>
        </Modal.Header>
        <Modal.Body>
          <Form />
        </Modal.Body>
      </Modal>
      <CloseModalConfirmationDialog />
    </>
  );
};

export default AddEditResourceAccessRuleModal;
