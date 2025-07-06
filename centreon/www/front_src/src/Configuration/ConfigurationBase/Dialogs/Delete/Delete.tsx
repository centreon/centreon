import { JSX } from 'react';
import { Trans, useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';
import { Typography } from '@mui/material';

import useDelete from './useDelete';

import { labelCancel, labelDelete } from '../../translatedLabels';

const DeleteDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const {
    close,
    confirm,
    isMutating,
    isOpened,
    headerText,
    getBodyText,
    getBodyTextVar
  } = useDelete();

  return (
    <Modal open={isOpened} size="large" onClose={close}>
      <Modal.Header>{headerText}</Modal.Header>
      <Modal.Body>
        <Typography>
          <Trans
            defaults={getBodyText()}
            values={getBodyTextVar()}
            components={{ bold: <strong /> }}
          />
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
