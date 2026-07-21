import { useAtomValue, useSetAtom } from 'jotai';
import { isNotNil } from 'ramda';
import { useCallback, useMemo } from 'react';

import { Modal } from '../../Modal';
import { askBeforeCloseFormModalAtom, openFormModalAtom } from '../atoms';
import Buttons from './Buttons';

const AddModal = ({
  title,
  Form,
  modalSize = 'medium'
}: {
  title: string;
  Form: (props: { Buttons: () => JSX.Element }) => JSX.Element;
  modalSize?: 'small' | 'medium' | 'large' | 'xlarge' | 'fullscreen';
}): JSX.Element => {
  const setAskBeforeCloseFormModal = useSetAtom(askBeforeCloseFormModalAtom);

  const openFormModal = useAtomValue(openFormModalAtom);

  const isModalOpen = useMemo(
    () => isNotNil(openFormModal) && openFormModal === 'add',
    [openFormModal]
  );

  const openAskBeforeClose = useCallback(
    () => setAskBeforeCloseFormModal(true),
    [setAskBeforeCloseFormModal]
  );

  return (
    <Modal
      onClose={openAskBeforeClose}
      open={isModalOpen}
      size={modalSize as 'small' | 'medium' | 'large' | 'xlarge' | 'fullscreen'}
    >
      <Modal.Header>{title}</Modal.Header>
      <Modal.Body>
        <Form Buttons={Buttons} />
      </Modal.Body>
    </Modal>
  );
};

export default AddModal;
