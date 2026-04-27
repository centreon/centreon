import { Form } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { Buttons } from '../Components';
import { useInputs } from './useInputs';
import { useInstallCommand } from './useInstallCommand';
import { useValidationSchema } from './useValidationSchema';

import { labelCreateNewPoller } from '../../../translatedLabels';
import {
  type CloudInstallCommandFormValues,
  PollerEnvironment
} from '../models';

const initialValues: CloudInstallCommandFormValues = {
  environment: PollerEnvironment.VM,
  pollerName: '',
  token: []
};

const InstallCommandModal = (): ReactElement => {
  const { t } = useTranslation();

  const { isOpen, close, submit } = useInstallCommand();
  const inputs = useInputs();
  const validationSchema = useValidationSchema();

  return (
    <Modal onClose={close} open={isOpen} size="medium">
      <Modal.Header>{t(labelCreateNewPoller)}</Modal.Header>
      <Modal.Body>
        <Form<CloudInstallCommandFormValues>
          Buttons={(): ReactElement => <Buttons close={close} />}
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

export default InstallCommandModal;
