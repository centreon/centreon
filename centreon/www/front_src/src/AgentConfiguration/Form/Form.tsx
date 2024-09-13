import { Form } from '@centreon/ui';
import {
  AgentConfigurationForm as AgentConfigurationFormModel,
  FormVariant
} from '../models';
import { useInputs } from './useInputs';
import { useValidationSchema } from './useValidationSchema';

interface Props {
  initialValues?: AgentConfigurationFormModel;
  variant: FormVariant;
}

const defaultInitialValues: AgentConfigurationFormModel = {
  name: '',
  type: null,
  pollers: [],
  configuration: {
    otelServerAddress: '',
    otelServerPort: '',
    confServerPort: ''
  },
  files: {
    otelCaCertificate: null,
    otelPrivateKey: null,
    otelPublicCertificate: null,
    confCertificate: null,
    confPrivateKey: null
  }
};

const AgentConfigurationForm = ({
  initialValues = defaultInitialValues
}: Props): JSX.Element => {
  const { groups, inputs } = useInputs();

  const validationSchema = useValidationSchema();

  const submit = () => undefined;

  return (
    <Form
      validationSchema={validationSchema}
      groups={groups}
      isCollapsible
      areGroupsOpen
      inputs={inputs}
      initialValues={initialValues}
      submit={submit}
    />
  );
};

export default AgentConfigurationForm;
