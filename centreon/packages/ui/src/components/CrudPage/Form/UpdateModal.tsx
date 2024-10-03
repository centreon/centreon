import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isNotNil } from 'ramda';
import { useCallback, useMemo } from 'react';
import { Modal } from '../..';
import { askBeforeCloseFormModalAtom, openFormModalAtom } from '../atoms';
import { useGetItem } from '../hooks/useGetItem';
import { Form as FormModel, GetItem } from '../models';
import Buttons from './Buttons';

const UpdateModal = <TItem extends { id: number; name: string }, TItemForm>({
  decoder,
  baseEndpoint,
  itemQueryKey,
  adapter,
  Form,
  title
}: GetItem<TItem, TItemForm> &
  Pick<FormModel<TItem, TItemForm>, 'Form'> & {
    title: string;
  }) => {
  const setAskBeforeCloseFormModal = useSetAtom(askBeforeCloseFormModalAtom);

  const openFormModal = useAtomValue(openFormModalAtom);

  const { initialValues, isLoading } = useGetItem({
    id: openFormModal,
    decoder,
    baseEndpoint,
    itemQueryKey,
    adapter
  });

  const isModalOpen = useMemo(
    () => isNotNil(openFormModal) && !equals('add', openFormModal),
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
          <Form
            initialValues={initialValues}
            Buttons={Buttons}
            isLoading={isLoading}
          />
        </Modal.Body>
      </Modal>
    </>
  );
};

export default UpdateModal;
