import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import { Trans, useTranslation } from 'react-i18next';

import {
  labelCancel,
  labelEnable,
  labelEnableToken,
  labelMsgConfirmationEnableToken
} from '../../translatedLabels';
import useEnable from './useEnable';

const EnableDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { close, confirm, isMutating, isOpened, name } = useEnable();

  return (
    <Modal onClose={close} open={isOpened} size="large">
      <Modal.Header>{t(labelEnableToken)}</Modal.Header>
      <Modal.Body>
        <Typography>
          <Trans
            components={{ bold: <strong /> }}
            defaults={labelMsgConfirmationEnableToken}
            values={{ tokenName: name }}
          />
        </Typography>
      </Modal.Body>
      <Modal.Actions
        disabled={isMutating}
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelEnable)
        }}
        onCancel={close}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default EnableDialog;
