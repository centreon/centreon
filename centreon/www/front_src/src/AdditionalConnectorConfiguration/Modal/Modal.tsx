import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Modal } from '@centreon/ui/components';

import {
  labelCancel,
  labelCreateConnectorConfiguration,
  labelDescription,
  labelName,
  labelCreate,
  labelUpdate,
  labelUpdateConnectorConfiguration
} from '../translatedLabels';

import AdditionalConnectorsForm from './Form';
import useAdditionalConnectorModal from './useModal';

const AdditionalConnectorModal = (): JSX.Element => {
  const { t } = useTranslation();

  const { closeDialog, isDialogOpen, submit, variant } =
    useAdditionalConnectorModal();

  const formLabels = {
    actions: {
      cancel: t(labelCancel),
      submit: {
        create: t(labelCreate),
        update: t(labelUpdate)
      }
    },
    entity: {
      description: t(labelDescription),
      name: t(labelName)
    }
  };

  const labelHeader = equals(variant, 'create')
    ? labelCreateConnectorConfiguration
    : labelUpdateConnectorConfiguration;

  return (
    <Modal open={isDialogOpen} size="large" onClose={closeDialog}>
      <Modal.Header>{t(labelHeader)}</Modal.Header>
      <Modal.Body>
        <AdditionalConnectorsForm
          labels={formLabels}
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
