import { Modal } from '@centreon/ui/components';
import { useAtom } from 'jotai';
import { isNotNil } from 'ramda';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { openFormModalAtom } from '../atoms';
import { FormVariant } from '../models';
import { labelAddAgentConfiguration } from '../translatedLabels';
import AgentConfigurationForm from './Form';

const AddModal = () => {
  const { t } = useTranslation();
  const [askBeforeClose, setAskBeforeClose] = useState(false);

  const [openFormModal, setOpenFormModal] = useAtom(openFormModalAtom);

  const isModalOpen = useMemo(() => isNotNil(openFormModal), [openFormModal]);

  const close = useCallback(() => setOpenFormModal(null), []);
  const openAskBeforeClose = useCallback(() => setAskBeforeClose(true), []);

  return (
    <>
      <Modal open={isModalOpen} onClose={openAskBeforeClose} size="large">
        <Modal.Header>{t(labelAddAgentConfiguration)}</Modal.Header>
        <Modal.Body>
          <AgentConfigurationForm variant={FormVariant.Add} />
        </Modal.Body>
      </Modal>
    </>
  );
};

export default AddModal;
