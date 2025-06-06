import { Modal } from '@centreon/ui/components';
import { Typography } from '@mui/material';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isNotNil } from 'ramda';
import { useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { askBeforeCloseFormModalAtom, openFormModalAtom } from '../atoms';
import { labelAddAgentConfiguration } from '../translatedLabels';
import AgentConfigurationForm from './Form';
import { useStyles } from './Modal.styles';

const AddModal = () => {
  const { classes } = useStyles();

  const { t } = useTranslation();
  const setAskBeforeCloseFormModal = useSetAtom(askBeforeCloseFormModalAtom);

  const openFormModal = useAtomValue(openFormModalAtom);

  const isModalOpen = useMemo(
    () => isNotNil(openFormModal) && equals('add', openFormModal),
    [openFormModal]
  );

  const openAskBeforeClose = useCallback(
    () => setAskBeforeCloseFormModal(true),
    []
  );

  return (
    <>
      <Modal open={isModalOpen} onClose={openAskBeforeClose} size="xlarge">
        <Modal.Header>
          <Typography className={classes.modalHeader}>
            {t(labelAddAgentConfiguration)}
          </Typography>
        </Modal.Header>
        <Modal.Body>
          <AgentConfigurationForm />
        </Modal.Body>
      </Modal>
    </>
  );
};

export default AddModal;
