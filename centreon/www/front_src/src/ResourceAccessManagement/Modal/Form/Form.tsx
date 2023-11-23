import { ReactElement } from 'react';

import { Form as FormComponent } from '@centreon/ui';

import FormButtons from './componenets/FormButtons';
import useFormInputs from './useFormInputs';
import { getEmptyInitialValues } from './initialValues';
import useFormSubmit from './useFormSubmit';
import useValidationSchema from './useValidationSchema';

const Form = (): ReactElement => {
  const { groups, inputs } = useFormInputs();
  const initialValues = getEmptyInitialValues();
  const { submit } = useFormSubmit();
  const { validationSchema } = useValidationSchema();

  return (
    <FormComponent
      Buttons={FormButtons}
      groups={groups}
      initialValues={initialValues}
      inputs={inputs}
      submit={submit}
      validationSchema={validationSchema}
    />
  );
};

export default Form;
