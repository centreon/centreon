import { SelectEntry } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';
import { Typography } from '@mui/material';
import { useAtom } from 'jotai';
import { useCallback, useRef } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { itemToDeleteAtom } from '../atoms';
import {
  labelCancel,
  labelDelete,
  labelDeleteAgent,
  labelDeletePoller
} from '../translatedLabels';

const DeleteModal = (): JSX.Element => {
  const { t } = useTranslation();

  const itemToDeleteRef = useRef<{
    agent: SelectEntry;
    poller?: SelectEntry;
  } | null>(null);

  const [itemToDelete, setItemToDelete] = useAtom(itemToDeleteAtom);

  const isOpen = Boolean(itemToDelete);

  const close = useCallback(() => {
    setItemToDelete(null);
  }, []);

  if (isOpen) {
    itemToDeleteRef.current = itemToDelete;
  }

  const hasPoller = Boolean(itemToDeleteRef?.current?.poller);

  const poller = itemToDeleteRef.current?.poller?.name;
  const agent = itemToDeleteRef.current?.agent?.name;

  return (
    <Modal open={isOpen} onClose={close} size="lg">
      <Modal.Header>
        {t(hasPoller ? labelDeletePoller : labelDeleteAgent)}
      </Modal.Header>
      <Modal.Body>
        <Typography>
          {hasPoller ? (
            <Trans t={t}>
              You are going to delete the configuration for the{' '}
              <strong>{{ poller }}</strong> poller from the{' '}
              <strong>{{ agent }}</strong> agent configuration. All
              configuration parameters for this poller will be deleted. This
              action cannot be undone.
            </Trans>
          ) : (
            <Trans t={t}>
              You are going to delete the <strong>{{ agent }}</strong> agent
              configuration. All configuration parameters for this agent will be
              deleted. This action cannot be undone.
            </Trans>
          )}
        </Typography>
      </Modal.Body>
      <Modal.Actions
        isDanger
        onCancel={close}
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelDelete)
        }}
      />
    </Modal>
  );
};

export default DeleteModal;
