import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';
import { equals, isEmpty } from 'ramda';
import {
  labelCancel,
  labelDelete,
  labelDeleteHostGroup,
  labelDeleteHostGroupConfirmation,
  labelDeleteHostGroups,
  labelDeleteHostGroupsConfirmation
} from '../../../translatedLabels';
import useDelete from './useDelete';

const DeleteDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { close, confirm, isMutating, hostGroupsToDelete, count, name } =
    useDelete();

  return (
    <Modal open={!isEmpty(hostGroupsToDelete)} size="large" onClose={close}>
      <Modal.Header>
        {t(equals(count, 1) ? labelDeleteHostGroup : labelDeleteHostGroups)}
      </Modal.Header>
      <Modal.Body>
        <Typography
          dangerouslySetInnerHTML={{
            __html: equals(count, 1)
              ? t(labelDeleteHostGroupConfirmation, { name })
              : t(labelDeleteHostGroupsConfirmation, { count })
          }}
        />
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
