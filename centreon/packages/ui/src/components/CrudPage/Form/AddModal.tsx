import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isNotNil } from 'ramda';
import { useCallback, useMemo } from 'react';
import { Modal } from '../../Modal';
import { askBeforeCloseFormModalAtom, openFormModalAtom } from '../atoms';
import Buttons from './Buttons';

const AddModal = ({ title, Form }): JSX.Element => {
  const setAskBeforeCloseFormModal = useSetAtom(askBeforeCloseFormModalAtom);

  const openFormModal = useAtomValue(openFormModalAtom);

  const isModalOpen = useMemo(
    () => isNotNil(openFormModal) && equals('add', openFormModal),
    [openFormModal]
  );

  const openAskBeforeClose = useCallback(
    () => setAskBeforeCloseFormModal(true),
    []
  );

  return (
    <>
      <Modal open={isModalOpen} onClose={openAskBeforeClose} size="xlarge">
        <Modal.Header>{title}</Modal.Header>
        <Modal.Body>
          <Form Buttons={Buttons} />
        </Modal.Body>
      </Modal>
    </>
  );
};

export default AddModal;
