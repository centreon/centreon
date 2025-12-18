import { Typography } from '@mui/material';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';
import { Button, Modal } from '@centreon/ui/components';

import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

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
  const { showSuccessMessage, showErrorMessage } = useSnackbar();
  const { t } = useTranslation();

  const { mutateAsync } = useMutationQuery({
    baseEndpoint: '',
    getEndpoint: () => closeTicketEndpoint,
    method: Method.POST,
    onMutate: () => {
      setResourcesToCloseTicket([]);
    },
    onSuccess: (data) => {
      if (!equals(data?.code, 0)) {
        showErrorMessage(data?.msg);
        return;
      }
      showSuccessMessage(t(labelTicketClosed));
    }
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
          rule_id: `${providerID}`,
          selection: resource?.serviceID
            ? `${resource?.hostID};${resource?.serviceID}`
            : `${resource?.hostID}`
        }
      }
    });
  }, [resource]);

  return (
    <Modal hasCloseButton onClose={close} open={isOpen}>
      <Modal.Header> {t(labelCloseATicket)} </Modal.Header>
      <Modal.Body>
        <Typography>
          {t(labelTicketWillBeClosedInTheProvider, { id: resource?.ticketId })}
        </Typography>
      </Modal.Body>
      <Modal.Actions>
        <Button onClick={close} variant="secondary">
          {t(labelCancel)}
        </Button>
        <Button onClick={confirm}>{t(labelConfirm)}</Button>
      </Modal.Actions>
    </Modal>
  );
};

export default CloseTicketModal;
