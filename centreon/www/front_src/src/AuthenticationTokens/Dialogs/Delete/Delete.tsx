import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import { Trans, useTranslation } from 'react-i18next';

import {
  labelCancel,
  labelDelete,
  labelDeleteToken,
  labelMsgConfirmationDeletionToken
} from '../../translatedLabels';
import useDelete from './useDelete';

const DeleteDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { close, confirm, isMutating, isOpened, name } = useDelete();

  return (
    <Modal onClose={close} open={isOpened} size="large">
      <Modal.Header>{t(labelDeleteToken)}</Modal.Header>
      <Modal.Body>
        <Typography>
          <Trans
            components={{ bold: <strong /> }}
            defaults={labelMsgConfirmationDeletionToken}
            values={{ tokenName: name }}
          />
        </Typography>
      </Modal.Body>
      <Modal.Actions
        disabled={isMutating}
        isDanger
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
