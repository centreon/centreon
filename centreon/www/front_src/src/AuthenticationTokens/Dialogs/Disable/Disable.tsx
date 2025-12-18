import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import { Trans, useTranslation } from 'react-i18next';

import {
  labelCancel,
  labelDisable,
  labelDisableToken,
  labelMsgConfirmationDisableToken
} from '../../translatedLabels';
import useDisable from './useDisable';

const DisableDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { close, confirm, isMutating, isOpened, name } = useDisable();

  return (
    <Modal onClose={close} open={isOpened} size="large">
      <Modal.Header>{t(labelDisableToken)}</Modal.Header>
      <Modal.Body>
        <Typography>
          <Trans
            components={{ bold: <strong /> }}
            defaults={labelMsgConfirmationDisableToken}
            values={{ tokenName: name }}
          />
        </Typography>
      </Modal.Body>
      <Modal.Actions
        disabled={isMutating}
        isDanger
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

export default DisableDialog;
