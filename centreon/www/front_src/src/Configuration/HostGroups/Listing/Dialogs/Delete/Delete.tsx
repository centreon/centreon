import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import {} from '@centreon/ui';
import { Modal } from '@centreon/ui/components';
import { equals, isEmpty } from 'ramda';
import {} from '../../../api/endpoints';
import {
  labelCancel,
  labelDelete,
  labelDeleteConfirmationText,
  labelDeleteConfirmationTitle
} from '../../../translatedLabels';
import useDelete from './useDelete';

const DeleteDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { close, confirm, isMutating, hostGroupsToDelete } = useDelete();

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
