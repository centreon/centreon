import { useFormikContext } from 'formik';
import { path, split } from 'ramda';

import { AdditionalConnectorConfiguration } from '../../models';

interface UseParameterState {
  changeParameterValue: (event) => void;
  getError: (name: string) => string | undefined;
  handleBlur;
}

const useParameter = ({ index }: { index: number }): UseParameterState => {
  const { setFieldValue, errors, touched, handleBlur } =
    useFormikContext<AdditionalConnectorConfiguration>();

  const getError = (name: string): string | undefined => {
    const fieldNamePath = split('.', `parameters.vcenters.${index}.${name}`);

    const error = path(fieldNamePath, touched)
      ? (path(fieldNamePath, errors) as string)
      : undefined;

    return error;
  };

  const changeParameterValue = (event): void => {
    setFieldValue(
      `parameters.vcenters.${index}.${event.target.name}`,
      event.target.value
    );
  };

  return {
    changeParameterValue,
    getError,
    handleBlur
  };
};

export default useParameter;
