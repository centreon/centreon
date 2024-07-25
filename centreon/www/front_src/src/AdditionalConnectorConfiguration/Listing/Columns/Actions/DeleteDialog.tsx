import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';

import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';
import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { connectorsToDeleteAtom } from '../../atom';
import {
  labelCancel,
  labelDelete,
  labelDeleteAdditionalConnectorConfiguration,
  labelAdditionalConnectorDeleted,
  labelDeleteAdditionalConnectorDescription,
  labelSomeConnectorsMayNotWorkAnymore
} from '../../../translatedLabels';
import { getAdditionalConnectorEndpoint } from '../../../api/endpoints';

const DeleteConnectorDialog = (): JSX.Element => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const [connectorToDelete, setConnectorToDelete] = useAtom(
    connectorsToDeleteAtom
  );

  const close = (): void => {
    setConnectorToDelete(null);
  };

  const { isMutating, mutateAsync: deleteConnector } = useMutationQuery({
    getEndpoint: () => getAdditionalConnectorEndpoint(connectorToDelete?.id),
    method: Method.DELETE,
    onSettled: close,
    onSuccess: () => {
      showSuccessMessage(t(labelAdditionalConnectorDeleted));
      queryClient.invalidateQueries({ queryKey: ['listConnectors'] });
    }
  });

  const confirm = (): void => {
    deleteConnector({});
  };

  return (
    <Modal open={Boolean(connectorToDelete)} size="large" onClose={close}>
      <Modal.Header>
        {t(labelDeleteAdditionalConnectorConfiguration)}
      </Modal.Header>
      <Modal.Body>
        <Typography>
          {t(labelDeleteAdditionalConnectorDescription, {
            name: connectorToDelete?.name
          })}
        </Typography>
        <Typography>{t(labelSomeConnectorsMayNotWorkAnymore)}</Typography>
      </Modal.Body>
      <Modal.Actions
        isDanger
        disabled={isMutating}
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelDelete)
        }}
        onCancel={close}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default DeleteConnectorDialog;
