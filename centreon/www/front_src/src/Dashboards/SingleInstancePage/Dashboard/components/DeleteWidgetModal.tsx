import { useRef } from 'react';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import { widgetToDeleteAtom } from '../atoms';
import useDeleteWidget from '../hooks/useDeleteWidget';
import {
  labelCancel,
  labelDelete,
  labelDeleteWidget,
  labelTheWidgetWillBeDeleted,
  labelTheWidgetWillBeDeletedWithName
} from '../translatedLabels';

const DeleteWidgetModal = (): JSX.Element => {
  const widgetRef = useRef('');

  const { t } = useTranslation();
  const [widgetToDelete, setWidgetToDelete] = useAtom(widgetToDeleteAtom);

  const { deleteWidget } = useDeleteWidget();

  const confirm = (): void => {
    deleteWidget(widgetToDelete?.id as string)();
    close();
  };

  const close = (): void => {
    setWidgetToDelete(null);
  };

  if (widgetToDelete?.name) {
    widgetRef.current = widgetToDelete?.name;
  }

  return (
    <Modal open={Boolean(widgetToDelete)} onClose={close}>
      <Modal.Header>{t(labelDeleteWidget)}</Modal.Header>
      <Modal.Body>
        {t(
          widgetToDelete?.name
            ? labelTheWidgetWillBeDeletedWithName
            : labelTheWidgetWillBeDeleted,
          { name: widgetRef.current }
        )}
      </Modal.Body>
      <Modal.Actions
        isDanger
        labels={{
          cancel: labelCancel,
          confirm: labelDelete
        }}
        onCancel={close}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default DeleteWidgetModal;
