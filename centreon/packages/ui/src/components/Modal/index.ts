import { Modal as ModalRoot } from './Modal';
import { ModalHeader } from './ModalHeader';
import { ModalBody } from './ModalBody';
import { ModalActions } from './ModalActions';

export const Modal = Object.assign(ModalRoot, {
  Actions: ModalActions,
  Body: ModalBody,
  Header: ModalHeader
});
