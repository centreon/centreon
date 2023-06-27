import { FormikHandlers, useFormik } from 'formik';
import { propEq, reject } from 'ramda';

import { DashboardShare } from '../models';

interface UseShareFormProps {
  shares: Array<DashboardShare>;
}

interface UseShareFormState extends Pick<FormikHandlers, 'handleChange'> {
  dirty: boolean;
  getInputName: (index: number) => string;
  removeContact: (id: number) => () => void;
  values: Array<DashboardShare>;
}

const useShareForm = ({ shares }: UseShareFormProps): UseShareFormState => {
  const { handleChange, values, setValues, dirty } = useFormik<
    Array<DashboardShare>
  >({
    initialValues: shares,
    onSubmit: () => undefined
  });

  const getInputName = (index: number): string => `${index}.role`;

  const removeContact = (id: number) => (): void => {
    setValues((currentValues) => reject(propEq('id', id), currentValues));
  };

  return {
    dirty,
    getInputName,
    handleChange,
    removeContact,
    values
  };
};

export default useShareForm;
