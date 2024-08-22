import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Modal } from '@centreon/ui/components';

import {
  labelCreateConnectorConfiguration,
  labelUpdateConnectorConfiguration
} from '../translatedLabels';

import AdditionalConnectorForm from './Form/Form';
import useAdditionalConnectorModal from './useModal';

const AdditionalConnectorModal = (): JSX.Element => {
  const { t } = useTranslation();

  const { closeDialog, isDialogOpen, submit, variant, connector } =
    useAdditionalConnectorModal();

  const labelHeader = equals(variant, 'create')
    ? labelCreateConnectorConfiguration
    : labelUpdateConnectorConfiguration;

  return (
    <Modal
      data-testid="Modal"
      open={isDialogOpen}
      size="large"
      onClose={closeDialog}
    >
      <Modal.Header>{t(labelHeader)}</Modal.Header>
      <Modal.Body>
        <AdditionalConnectorForm
          connectorId={connector?.id}
          variant={variant}
          onCancel={closeDialog}
          onSubmit={submit}
        />
      </Modal.Body>
    </Modal>
  );
};

export default AdditionalConnectorModal;
