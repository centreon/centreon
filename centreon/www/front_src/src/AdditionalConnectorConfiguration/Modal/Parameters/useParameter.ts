import { useFormikContext } from 'formik';
import { equals, includes, path, split } from 'ramda';
import { useAtomValue } from 'jotai';

import { AdditionalConnectorConfiguration, ParameterKeys } from '../models';
import { dialogStateAtom } from '../../atoms';

interface UseParameterState {
  changeParameterValue: (event) => void;
  getError: (name: string) => string | undefined;
  getFieldType: (name) => string;
  getIsFieldRequired: (name: string) => boolean;
  handleBlur;
}

const useParameter = ({ index }: { index: number }): UseParameterState => {
  const { variant } = useAtomValue(dialogStateAtom);

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

  const getIsFieldRequired = (name): boolean => {
    if (
      equals(variant, 'create') ||
      !includes(name, [ParameterKeys.username, ParameterKeys.password])
    ) {
      return true;
    }

    return false;
  };

  return {
    changeParameterValue,
    getError,
    getFieldType,
    getIsFieldRequired,
    handleBlur
  };
};

export default useParameter;
