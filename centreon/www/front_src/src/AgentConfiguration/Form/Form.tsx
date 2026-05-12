import { Form } from '@centreon/ui';

import { FormikHelpers } from 'formik';
import { find, isNil, propEq } from 'ramda';
import { ReactElement } from 'react';
import { Schema } from 'yup';

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

const defaultInitialValues: AgentConfigurationFormModel = {
  configuration: { port: 4317 } as AgentConfigurationFormModel['configuration'],
  connectionMode: find(
    propEq('secure', 'id'),
    connectionModes
  ) as AgentConfigurationFormModel['connectionMode'],
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
      submit={
        submit as unknown as (
          values: AgentConfigurationFormModel,
          bag: FormikHelpers<AgentConfigurationFormModel>
        ) => void
      }
      validationSchema={
        validationSchema as unknown as Schema<AgentConfigurationFormModel>
      }
    />
  );
};

export default AgentConfigurationForm;
