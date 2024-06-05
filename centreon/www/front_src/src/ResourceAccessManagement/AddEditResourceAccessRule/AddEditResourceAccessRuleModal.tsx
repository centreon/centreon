import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtom, useSetAtom } from 'jotai';

import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import {
  labelCreateResourceAccessRule,
  labelEditResourceAccessRule
} from '../translatedLabels';
import { isCloseModalConfirmationDialogOpenAtom, isDirtyAtom } from '../atom';

import useResourceAccessRuleModal from './useResourceAccessRuleModal';
import useModalStyles from './Modal.styles';
import { Form } from './Form';
import CloseModalConfirmationDialog from './CloseModalConfirmationDialog';

const AddEditResourceAccessRuleModal = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useModalStyles();

  const { closeModal, isModalOpen, mode } = useResourceAccessRuleModal();
  const [isDirty, setIsDirty] = useAtom(isDirtyAtom);
  const setIsDialogOpen = useSetAtom(isCloseModalConfirmationDialogOpenAtom);

  const askBeforeClose = (): void => {
    if (isDirty) {
      setIsDialogOpen(true);

      return;
    }

    setIsDirty(false);
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
      <Modal open={isModalOpen} size="xlarge" onClose={askBeforeClose}>
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
