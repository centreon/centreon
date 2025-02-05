import { useQueryClient } from '@tanstack/react-query';
import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';

import { equals, isEmpty, pluck } from 'ramda';
import {
  bulkDeleteHostGroupEndpoint,
  getHostGroupEndpoint
} from '../../api/endpoints';
import { hostGroupsToDeleteAtom } from '../../atoms';
import {
  labelCancel,
  labelDelete,
  labelDeleteConfirmationText,
  labelDeleteConfirmationTitle,
  labelHostGroupDeleted
} from '../../translatedLabels';

const DeleteDialog = (): JSX.Element => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const [hostGroupsToDelete, setHostGroupsToDelete] = useAtom(
    hostGroupsToDeleteAtom
  );

  const close = (): void => {
    setHostGroupsToDelete([]);
  };

  const { isMutating, mutateAsync: deleteHostGroup } = useMutationQuery({
    getEndpoint: () =>
      equals(hostGroupsToDelete.length, 1)
        ? getHostGroupEndpoint({ id: hostGroupsToDelete[0]?.id })
        : bulkDeleteHostGroupEndpoint,
    method: equals(hostGroupsToDelete.length, 1) ? Method.DELETE : Method.POST,
    onSettled: close,
    onSuccess: () => {
      showSuccessMessage(t(labelHostGroupDeleted));
      queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
    }
  });

  const confirm = (): void => {
    deleteHostGroup({ payload: { ids: pluck('id', hostGroupsToDelete) } });
  };

  return (
    <Modal open={!isEmpty(hostGroupsToDelete)} size="large" onClose={close}>
      <Modal.Header>{t(labelDeleteConfirmationTitle)}</Modal.Header>
      <Modal.Body>
        <Typography>
          {t(labelDeleteConfirmationText, {
            name: equals(hostGroupsToDelete.length, 1)
              ? hostGroupsToDelete[0]?.name
              : hostGroupsToDelete.length
          })}
        </Typography>
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

export default DeleteDialog;
