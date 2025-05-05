import { Typography } from '@mui/material';

import { Trans, useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import useEnable from './useEnable';

import {
  labelCancel,
  labelEnable,
  labelEnableToken,
  labelMsgConfirmationEnableToken
} from '../../translatedLabels';

const EnableDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { close, confirm, isMutating, isOpened, name } = useEnable();

  return (
    <Modal open={isOpened} size="large" onClose={close}>
      <Modal.Header>{t(labelEnableToken)}</Modal.Header>
      <Modal.Body>
        <Typography>
          <Trans
            defaults={labelMsgConfirmationEnableToken}
            values={{ tokenName: name }}
            components={{ bold: <strong /> }}
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
