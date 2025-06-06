import { SelectEntry } from '@centreon/ui';
import { Button, Modal } from '@centreon/ui/components';
import { Box, CircularProgress, Typography } from '@mui/material';
import { useAtom } from 'jotai';
import { useCallback, useRef } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { itemToDeleteAtom } from '../atoms';
import { useDeletePollerAgent } from '../hooks/useDeletePollerAgent';
import {
  labelCancel,
  labelDelete,
  labelDeleteAgent,
  labelDeleteAgentConfirmation,
  labelDeletePoller,
  labelDeletePollerConfirmation
} from '../translatedLabels';

const DeleteModal = (): JSX.Element => {
  const { t } = useTranslation();

  const itemToDeleteRef = useRef<{
    agent: SelectEntry;
    poller?: SelectEntry;
  } | null>(null);

  const [itemToDelete, setItemToDelete] = useAtom(itemToDeleteAtom);

  const { isMutating, deleteItem } = useDeletePollerAgent();

  const isOpen = Boolean(itemToDelete);

  const close = useCallback(() => {
    setItemToDelete(null);
  }, []);

  const confirm = useCallback(() => {
    deleteItem({
      agentId: itemToDeleteRef.current?.agent.id,
      pollerId: itemToDeleteRef.current?.poller?.id
    }).then(close);
  }, [itemToDelete]);

  if (isOpen) {
    itemToDeleteRef.current = itemToDelete;
  }

  const hasPoller = Boolean(itemToDeleteRef.current?.poller);

  const poller = itemToDeleteRef.current?.poller?.name;
  const agent = itemToDeleteRef.current?.agent?.name;

  return (
    <Modal open={isOpen} onClose={close} size="large">
      <Modal.Header>
        {t(hasPoller ? labelDeletePoller : labelDeleteAgent)}
      </Modal.Header>
      <Modal.Body>
        <Typography>
          <Trans
            defaults={
              hasPoller
                ? labelDeletePollerConfirmation
                : labelDeleteAgentConfirmation
            }
            values={hasPoller ? { poller, agent } : { agent }}
            components={{ bold: <strong /> }}
          />
        </Typography>
      </Modal.Body>
      <Box
        sx={{
          display: 'flex',
          flexDirection: 'row',
          gap: 2,
          justifyContent: 'flex-end'
        }}
      >
        {isMutating && <CircularProgress size={20} />}
        <Button variant="ghost" onClick={close} disabled={isMutating}>
          {t(labelCancel)}
        </Button>
        <Button isDanger onClick={confirm} disabled={isMutating}>
          {t(labelDelete)}
        </Button>
      </Box>
    </Modal>
  );
};

export default DeleteModal;
