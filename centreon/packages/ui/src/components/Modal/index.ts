import { Modal as ModalRoot } from './Modal';
import { ModalActions } from './ModalActions';
import { ModalBody } from './ModalBody';
import { ModalHeader } from './ModalHeader';

export { ConfirmationModal } from './ConfirmationModal/ConfirmationModal';

export const Modal = Object.assign(ModalRoot, {
  Actions: ModalActions,
  Body: ModalBody,
  Header: ModalHeader
});

export type { ModalActionsLabels } from './ModalActions';
