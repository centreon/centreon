import { Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import { labelCancel, labelDelete } from '../../translatedLabels';

import { sanitizedHTML } from '@centreon/ui';
import useDelete from './useDelete';

const DeleteDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { close, confirm, isMutating, isOpened, headerContent, bodyContent } =
    useDelete();

  return (
    <Modal open={isOpened} size="large" onClose={close}>
      <Modal.Header>{headerContent}</Modal.Header>
      <Modal.Body>
        <Typography>
          {sanitizedHTML({ initialContent: bodyContent })}
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
