import { Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import { labelCancel, labelDelete } from '../../../translatedLabels';

import useDelete from './useDelete';

const DeleteDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const { close, confirm, isMutating, isOpened, headerContent, bodyContent } =
    useDelete();

  return (
    <Modal open={isOpened} size="large" onClose={close}>
      <Modal.Header>{headerContent}</Modal.Header>
      <Modal.Body>
        <Typography
          // biome-ignore lint/security/noDangerouslySetInnerHtml: <explanation>
          dangerouslySetInnerHTML={{
            __html: bodyContent
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
