import { Typography } from '@mui/material';

import { Trans, useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import useDisable from './useDisable';

import {
  labelCancel,
  labelDisable,
  labelDisbleToken,
  labelMsgConfirmationDisableToken
} from '../../translatedLabels';

const DeleteDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { close, confirm, isMutating, isOpened, name } = useDisable();

  return (
    <Modal open={isOpened} size="large" onClose={close}>
      <Modal.Header>{t(labelDisbleToken)}</Modal.Header>
      <Modal.Body>
        <Typography>
          <Trans
            defaults={labelMsgConfirmationDisableToken}
            values={{ tokenName: name }}
            components={{ bold: <strong /> }}
          />
        </Typography>
      </Modal.Body>
      <Modal.Actions
        isDanger
        disabled={isMutating}
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelDisable)
        }}
        onCancel={close}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default DeleteDialog;
