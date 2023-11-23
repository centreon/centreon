import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import {
  labelCreateResourceAccessRule,
  labelEditResourceAccessRule
} from '../translatedLabels';

import useResourceAccessRuleConfig from './useResourceAccessRuleConfig';
import { Form } from './Form';

const ResourceAccessRuleConfigModal = (): JSX.Element => {
  const { t } = useTranslation();

  const { closeModal, isModalOpen, mode } = useResourceAccessRuleConfig();

  const labels = useMemo(
    (): {
      modalTitle: {
        create: string;
        edit: string;
      };
    } => ({
      modalTitle: {
        create: t(labelCreateResourceAccessRule),
        edit: t(labelEditResourceAccessRule)
      }
    }),
    []
  );

  return (
    <Modal open={isModalOpen} onClose={closeModal}>
      <Modal.Header>{labels.modalTitle[mode]}</Modal.Header>
      <Modal.Body>
        <Form />
      </Modal.Body>
    </Modal>
  );
};

export default ResourceAccessRuleConfigModal;
