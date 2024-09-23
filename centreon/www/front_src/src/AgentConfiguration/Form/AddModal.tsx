import { Modal } from '@centreon/ui/components';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isNotNil } from 'ramda';
import { useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { agentTypeFormAtom, askBeforeCloseFormModalAtom, openFormModalAtom } from '../atoms';
import { AgentType } from '../models';
import { labelAddAgentConfiguration } from '../translatedLabels';
import AgentConfigurationForm from './Form';

const AddModal = () => {
  const { t } = useTranslation();
  const setAskBeforeCloseFormModal = useSetAtom(askBeforeCloseFormModalAtom);

  const openFormModal = useAtomValue(openFormModalAtom);
  const setAgentTypeForm = useSetAtom(agentTypeFormAtom);

  setAgentTypeForm(AgentType.Telegraf);

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
        <Modal.Header>{t(labelAddAgentConfiguration)}</Modal.Header>
        <Modal.Body>
          <AgentConfigurationForm />
        </Modal.Body>
      </Modal>
    </>
  );
};

export default AddModal;
