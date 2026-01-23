import { Form } from '@centreon/ui';

import { find, isNil, propEq } from 'ramda';
import { ReactElement } from 'react';

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
  configuration: { port: 4317 },
  connectionMode: find(propEq('secure', 'id'), connectionModes),
  name: '',
  pollers: [],
  type: null
};

const AgentConfigurationForm = ({
  initialValues,
  isLoading
}: Props): ReactElement => {
  const { classes } = useFormStyles();

  const { groups, inputs } = useInputs();

  const validationSchema = useValidationSchema();
  const { submit } = useAddUpdateAgentConfiguration();

  const values = isNil(initialValues) ? defaultInitialValues : initialValues;

  return (
    <Form<AgentConfigurationFormModel>
      areGroupsOpen
      Buttons={Buttons}
      enableReinitialize
      groups={groups}
      groupsClassName={classes.groups}
      initialValues={values}
      inputs={inputs}
      isCollapsible
      isLoading={isLoading}
      submit={submit}
      validationSchema={validationSchema}
    />
  );
};

export default AgentConfigurationForm;
