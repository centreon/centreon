import { Form } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';

import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { labelCreateNewPoller } from '../../../translatedLabels';
import Buttons from './Buttons';
import type { CloudInstallCommandFormValues } from './models';
import { useCloudInstallCommand } from './useCloudInstallCommand';
import { useInputs } from './useInputs';
import { useValidationSchema } from './useValidationSchema';

const initialValues: CloudInstallCommandFormValues = {
  environment: null,
  pollerName: '',
  token: null
};

const CloudInstallCommandModal = (): ReactElement => {
  const { t } = useTranslation();

  const { isOpen, close, submit } = useCloudInstallCommand();
  const inputs = useInputs();
  const validationSchema = useValidationSchema();

  return (
    <Modal onClose={close} open={isOpen} size="medium">
      <Modal.Header>{t(labelCreateNewPoller)}</Modal.Header>
      <Modal.Body>
        <Form<CloudInstallCommandFormValues>
          Buttons={Buttons}
          initialValues={initialValues}
          inputs={inputs}
          isCollapsible={false}
          submit={submit}
          validationSchema={validationSchema}
        />
      </Modal.Body>
    </Modal>
  );
};

export default CloudInstallCommandModal;
