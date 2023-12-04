import { ReactElement } from 'react';

import { Form as FormComponent } from '@centreon/ui';

import useFormInputs from '../FormInputs/useFormInputs';
import { getEmptyInitialValues } from '../FormInitialValues/initialValues';

import ActionButtons from './ActionButtons';
import useFormSubmit from './useFormSubmit';
import useValidationSchema from './useValidationSchema';

const Form = (): ReactElement => {
  const { groups, inputs } = useFormInputs();
  const initialValues = getEmptyInitialValues();

  const { submit } = useFormSubmit();

  const { validationSchema } = useValidationSchema();

  return (
    <FormComponent
      Buttons={ActionButtons}
      groups={groups}
      initialValues={initialValues}
      inputs={inputs}
      submit={submit}
      validationSchema={validationSchema}
    />
  );
};

export default Form;
