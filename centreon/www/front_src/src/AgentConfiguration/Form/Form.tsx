import { Form } from '@centreon/ui';
import { find, isNil, propEq } from 'ramda';
import { useAddUpdateAgentConfiguration } from '../hooks/useAddUpdateAgentConfiguration';
import { AgentConfigurationForm as AgentConfigurationFormModel } from '../models';
import Buttons from './Buttons';
import { useFormStyles } from './Modal.styles';
import { connectionModes, useInputs } from './useInputs';
import { useValidationSchema } from './useValidationSchema';

interface Props {
  initialValues?: AgentConfigurationFormModel;
  isLoading?: boolean;
}

const defaultInitialValues = {
  name: '',
  type: null,
  pollers: [],
  configuration: {},
  connectionMode: find(propEq('secure', 'id'), connectionModes)
};

const AgentConfigurationForm = ({
  initialValues,
  isLoading
}: Props): JSX.Element => {
  const { classes } = useFormStyles();

  const { groups, inputs } = useInputs();

  const validationSchema = useValidationSchema();
  const { submit } = useAddUpdateAgentConfiguration();

  const values = isNil(initialValues) ? defaultInitialValues : initialValues;

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
      initialValues={values}
      submit={submit}
      groupsClassName={classes.groups}
    />
  );
};

export default AgentConfigurationForm;
