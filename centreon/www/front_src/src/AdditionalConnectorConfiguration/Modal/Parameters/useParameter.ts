import { useFormikContext } from 'formik';
import { includes, path, split } from 'ramda';

import { AdditionalConnectorConfiguration, ParameterKeys } from '../models';

interface UsParameter {
  changeParameterValue: (event) => void;
  getError: (name: string) => string | undefined;
  getFieldType: (name) => string;
  handleBlur;
}

const useParameter = ({ index }: { index: number }): UsParameter => {
  const { setFieldValue, errors, touched, handleBlur } =
    useFormikContext<AdditionalConnectorConfiguration>();

  const getError = (name: string): string | undefined => {
    const fieldNamePath = split('.', `parameters.vcenters.${index}.${name}`);

    const error = path(fieldNamePath, touched)
      ? (path(fieldNamePath, errors) as string)
      : undefined;

    return error;
  };

  const getFieldType = (name): string =>
    includes(name, [ParameterKeys.username, ParameterKeys.password])
      ? 'password'
      : 'text';

  const changeParameterValue = (event): void => {
    setFieldValue(
      `parameters.vcenters.${index}.${event.target.name}`,
      event.target.value
    );
  };

  return { changeParameterValue, getError, getFieldType, handleBlur };
};

export default useParameter;
