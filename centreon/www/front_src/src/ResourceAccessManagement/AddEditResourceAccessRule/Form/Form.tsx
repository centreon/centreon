import { ReactElement } from 'react';

import { Form as FormComponent } from '@centreon/ui';

import useFormInitialValues from '../FormInitialValues/useFormInitialValues';
import useFormInputs from '../FormInputs/useFormInputs';

import ActionButtons from './ActionButtons';
import useFormSubmit from './useFormSubmit';
import useValidationSchema from './useValidationSchema';

const Form = (): ReactElement => {
  const { groups, inputs } = useFormInputs();
  const { initialValues, isLoading } = useFormInitialValues();

  const { submit } = useFormSubmit();

  const { validationSchema } = useValidationSchema();

  return (
    <FormComponent
      Buttons={ActionButtons}
      groups={groups}
      initialValues={initialValues}
      inputs={inputs}
      isLoading={isLoading}
      submit={submit}
      validationSchema={validationSchema}
    />
  );
};

export default Form;
