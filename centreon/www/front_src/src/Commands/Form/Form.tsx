import { Form } from '@centreon/ui';
import { isNil } from 'ramda';
import { ReactElement } from 'react';

import { useFormStyles } from './Modal.styles';
import { useInputs } from './useInputs';
import { useValidationSchema } from './useValidationSchema';

const defaultInitialValues = {
  name: ''
};

const CommandsForm = ({ Buttons }): ReactElement => {
  const { classes } = useFormStyles();

  const { groups, inputs } = useInputs();

  const validationSchema = useValidationSchema();
  const submit = () => undefined;

  const initialValues = defaultInitialValues;
  const isLoading = false;

  const values = isNil(initialValues) ? defaultInitialValues : initialValues;

  return (
    <Form
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

export default CommandsForm;
