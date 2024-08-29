import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';
import { Button, Modal } from '@centreon/ui/components';
import { Typography } from '@mui/material';
import { useAtom } from 'jotai';
import { closeTicketEndpoint } from '../../../api/endpoints';
import { resourcesToCloseTicketAtom } from '../../../atom';
import {
  labelCancel,
  labelCloseATicket,
  labelConfirm,
  labelTicketClosed,
  labelTicketWillBeClosedInTheProvider
} from '../../translatedLabels';

interface Props {
  providerID?: number;
}

const CloseTicketModal = ({ providerID }: Props): JSX.Element => {
  const [resourcesToCloseTicket, setResourcesToCloseTicket] = useAtom(
    resourcesToCloseTicketAtom
  );
  const { showSuccessMessage } = useSnackbar();
  const { t } = useTranslation();
  const { mutateAsync } = useMutationQuery({
    method: Method.POST,
    getEndpoint: () => closeTicketEndpoint,
    onSuccess: () => {
      showSuccessMessage(t(labelTicketClosed));
    },
    onMutate: () => setResourcesToCloseTicket([])
  });

  const resource = resourcesToCloseTicket[0];
  const isOpen = !!resource;

  const close = useCallback((): void => {
    setResourcesToCloseTicket([]);
  }, []);

  const confirm = useCallback(() => {
    mutateAsync({
      payload: {
        data: {
          selection: resource?.serviceID
            ? `${resource?.hostID};${resource?.serviceID}`
            : `${resource?.hostID}`,
          rule_id: `${providerID}`
        }
      }
    });
  }, [resource]);

  return (
    <Modal hasCloseButton open={isOpen} onClose={close}>
      <Modal.Header> {t(labelCloseATicket)} </Modal.Header>
      <Modal.Body>
        <Typography>{t(labelTicketWillBeClosedInTheProvider)}</Typography>{' '}
      </Modal.Body>
      <Modal.Actions>
        <Button variant="secondary" onClick={close}>
          {t(labelCancel)}
        </Button>
        <Button onClick={confirm}>{t(labelConfirm)}</Button>
      </Modal.Actions>
    </Modal>
  );
};

export default CloseTicketModal;
