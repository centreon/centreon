import { Form } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { equals, isNil, isNotNil } from 'ramda';
import { useEffect, useRef } from 'react';
import { agentTypeFormAtom } from '../atoms';
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

const getDefaultInitialValues = (
  agentType: AgentType | null
): AgentConfigurationFormModel => ({
  name: '',
  type: agentTypes.find(({ id }) => equals(id, agentType)),
  pollers: [],
  configuration: equals(agentType, AgentType.Telegraf)
    ? {
        otelServerAddress: '',
        otelServerPort: '',
        confServerPort: '',
        otelPrivateKey: '',
        otelCaCertificate: '',
        otelPublicCertificate: '',
        confPrivateKey: '',
        confCertificate: ''
      }
    : {
        isReverse: true,
        otlpReceiverAddress: '',
        otlpReceiverPort: '',
        otlpCertificate: '',
        otlpCaCertificate: '',
        otlpPrivateKey: '',
        hosts: [
          {
            address: '',
            port: '',
            certificate: '',
            key: ''
          }
        ]
      }
});

const AgentConfigurationForm = ({
  initialValues,
  isLoading
}: Props): JSX.Element => {
  const agentTypeForm = useAtomValue(agentTypeFormAtom);
  const previousAgentTypeFormRef = useRef(agentTypeForm);
  const { groups, inputs } = useInputs();

  const validationSchema = useValidationSchema();
  const { submit } = useAddUpdateAgentConfiguration();

  useEffect(() => {
    if (!equals(previousAgentTypeFormRef.current, agentTypeForm)) {
      previousAgentTypeFormRef.current = agentTypeForm;
    }
  }, [agentTypeForm]);

  console.log(agentTypeForm);

  return (
    <Form<AgentConfigurationFormModel>
      enableReinitialize
      Buttons={Buttons}
      validationSchema={validationSchema}
      isLoading={isLoading}
      groups={groups}
      isCollapsible
      areGroupsOpen
      inputs={inputs}
      initialValues={
        (!equals(previousAgentTypeFormRef.current, agentTypeForm) &&
          isNotNil(agentTypeForm) &&
          isNotNil(previousAgentTypeFormRef.current)) ||
        isNil(initialValues)
          ? getDefaultInitialValues(agentTypeForm)
          : initialValues
      }
      submit={submit}
    />
  );
};

export default AgentConfigurationForm;
