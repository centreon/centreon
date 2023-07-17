import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import {
  labelDeleteAWidget,
  labelDoYouWantToDeleteThisWidget
} from '../translatedLabels';
import { labelCancel, labelDelete } from '../../translatedLabels';

import useDeleteWidgetModal from './useDeleteWidgetModal';

const DeleteWidgetModal = (): JSX.Element => {
  const { t } = useTranslation();
  const { closeModal, deleteWidget, isModalOpened } = useDeleteWidgetModal();

  return (
    <Modal open={isModalOpened} onClose={closeModal}>
      <Modal.Header>{t(labelDeleteAWidget)}</Modal.Header>
      <Modal.Body>{t(labelDoYouWantToDeleteThisWidget)}</Modal.Body>
      <Modal.Actions
        isDanger
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelDelete)
        }}
        onCancel={closeModal}
        onConfirm={deleteWidget}
      />
    </Modal>
  );
};

export default DeleteWidgetModal;
