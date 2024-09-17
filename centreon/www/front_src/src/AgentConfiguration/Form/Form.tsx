import { Form } from '@centreon/ui';
import { equals } from 'ramda';
import { useAddUpdateAgentConfiguration } from '../hooks/useAddUpdateAgentConfiguration';
import {
  AgentConfigurationForm as AgentConfigurationFormModel,
  AgentType
} from '../models';
import Buttons from './Buttons';
import { agentTypes, useInputs } from './useInputs';
import { useValidationSchema } from './useValidationSchema';

interface Props {
  initialValues?: AgentConfigurationFormModel;
  isLoading?: boolean;
}

const defaultInitialValues: AgentConfigurationFormModel = {
  name: '',
  type: agentTypes.find(({ id }) => equals(id, AgentType.Telegraf)) || null,
  pollers: [],
  configuration: {
    otelServerAddress: '',
    otelServerPort: '',
    confServerPort: '',
    otelPrivateKey: '',
    otelCaCertificate: '',
    otelPublicCertificate: '',
    confPrivateKey: '',
    confCertificate: ''
  }
};

const AgentConfigurationForm = ({
  initialValues = defaultInitialValues,
  isLoading
}: Props): JSX.Element => {
  const { groups, inputs } = useInputs();

  const validationSchema = useValidationSchema();
  const { submit } = useAddUpdateAgentConfiguration();

  return (
    <Form<AgentConfigurationFormModel>
      Buttons={Buttons}
      validationSchema={validationSchema}
      isLoading={isLoading}
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
