import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';

import { DashboardDuplicationForm, Modal } from '@centreon/ui/components';
import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { connectorsToDuplicateAtom } from '../../atom';
import {
  labelCancel,
  labelDuplicate,
  labelName,
  labelDuplicateConnectorConfiguration,
  labelAdditionalConnectorDuplicated
} from '../../../translatedLabels';
import { additionalConnectorsEndpoint } from '../../api';

const DuplicateConnectorDialog = (): JSX.Element => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const [connectorsToDuplicate, setConnectorsToDuplicate] = useAtom(
    connectorsToDuplicateAtom
  );

  const close = (): void => {
    setConnectorsToDuplicate(null);
  };

  const { mutateAsync: duplicateConnector } = useMutationQuery({
    getEndpoint: () => additionalConnectorsEndpoint,
    method: Method.POST,
    onSettled: close,
    onSuccess: () => {
      showSuccessMessage(t(labelAdditionalConnectorDuplicated));
      queryClient.invalidateQueries({ queryKey: ['listConnectors'] });
    }
  });

  const submit = (): void => {
    duplicateConnector({});
  };

  const labels = {
    actions: {
      cancel: t(labelCancel),
      submit: {
        create: t(labelDuplicate)
      }
    },
    entity: {
      name: t(labelName)
    }
  };

  return (
    <Modal open={Boolean(connectorsToDuplicate)} onClose={close}>
      <Modal.Header>{t(labelDuplicateConnectorConfiguration)}</Modal.Header>
      <Modal.Body>
        <DashboardDuplicationForm
          labels={labels}
          name={`${connectorsToDuplicate?.name}_1`}
          onCancel={close}
          onSubmit={submit}
        />
      </Modal.Body>
    </Modal>
  );
};

export default DuplicateConnectorDialog;
