// TODO merge cleanup

import { FormikHandlers, useFormik } from 'formik';
import { lensPath, set } from 'ramda';

import { DashboardShare, DashboardShareForm } from '../models';

import useShareUpdate from './useShareUpdate';

interface UseShareFormProps {
  dashboardId?: number;
  shares: Array<DashboardShare>;
}

interface ToggleContactProps {
  index: number;
  value: boolean;
}

interface UseShareFormState extends Pick<FormikHandlers, 'handleChange'> {
  dirty: boolean;
  getInputName: (index: number) => string;
  submitForm: () => Promise<void>;
  toggleContact: (props: ToggleContactProps) => () => void;
  values: Array<DashboardShareForm>;
}

const formatSharesToFormValues = (
  shares: Array<DashboardShare>
): Array<DashboardShareForm> =>
  shares.map((share) => ({
    ...share,
    isRemoved: false
  }));

const useShareForm = ({
  shares,
  dashboardId
}: UseShareFormProps): UseShareFormState => {
  const { updateShares } = useShareUpdate(dashboardId);

  const { handleChange, values, setValues, dirty, submitForm } = useFormik<
    Array<DashboardShareForm>
  >({
    enableReinitialize: true,
    initialValues: formatSharesToFormValues(shares),
    onSubmit: (formValues) => updateShares(formValues)
  });

  const getInputName = (index: number): string => `${index}.role`;

  const toggleContact =
    ({ index, value }: ToggleContactProps) =>
    (): void => {
      setValues((currentValues) =>
        set(lensPath([index, 'isRemoved']), !value, currentValues)
      );
    };

  return {
    dirty,
    getInputName,
    handleChange,
    submitForm,
    toggleContact,
    values
  };
};

export default useShareForm;
