import { getEmptyInitialValues } from './initialValues';

interface UseFormState {
  initialValues: object;
  isLoading: boolean;
}

const useFormInitialValues = (): UseFormState => {
  const initialValues = getEmptyInitialValues();
  const isLoading = false;

  return { initialValues, isLoading };
};

export default useFormInitialValues;
