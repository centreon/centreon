import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Modal } from '@centreon/ui/components';

import {
  labelCreateConnectorConfiguration,
  labelUpdateConnectorConfiguration
} from '../translatedLabels';

import AdditionalConnectorsForm from './Form/Form';
import useAdditionalConnectorModal from './useModal';

const AdditionalConnectorModal = (): JSX.Element => {
  const { t } = useTranslation();

  const { closeDialog, isDialogOpen, submit, variant } =
    useAdditionalConnectorModal();

  const labelHeader = equals(variant, 'create')
    ? labelCreateConnectorConfiguration
    : labelUpdateConnectorConfiguration;

  return (
    <Modal open={isDialogOpen} size="large" onClose={closeDialog}>
      <Modal.Header>{t(labelHeader)}</Modal.Header>
      <Modal.Body>
        <AdditionalConnectorsForm
          resource={undefined}
          variant={variant}
          onCancel={closeDialog}
          onSubmit={submit}
        />
      </Modal.Body>
    </Modal>
  );
};

export default AdditionalConnectorModal;
