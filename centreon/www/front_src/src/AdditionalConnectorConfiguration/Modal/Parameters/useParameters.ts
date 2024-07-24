import { useFormikContext } from 'formik';
import { includes, remove } from 'ramda';

import { ConnectorConfiguration, Parameter, ParameterKeys } from '../models';
import { defaultParameters } from '../utils';

interface UsParameters {
  addParameterGroup: () => void;
  changeParameterValue: (index: number) => (event) => void;
  deleteParameterGroup: (index) => void;
  getError: (index: number) => (propertyName: string) => string | null;
  getFieldType: (name: string) => string;
  onBlur: (index) => (propertyName) => void;
  parameters: Array<Parameter>;
}

const useParameters = (): UsParameters => {
  const { values, setFieldValue, errors, touched, handleBlur } =
    useFormikContext<ConnectorConfiguration>();

  const getError =
    (index: number) =>
    (propertyName: string): string | null => {
      const isTouched = touched.parameters?.vcenters?.[index]?.[propertyName];

      const error = errors.parameters?.vcenters?.[index]?.[propertyName];

      const errorToDisplay = isTouched && error;

      return errorToDisplay;
    };

  const onBlur = (index) => (propertyName) => {
    handleBlur(`parameters.vcenters.${index}.${propertyName}`);
  };

  const addParameterGroup = (): void => {
    setFieldValue('parameters.vcenters', [
      ...values.parameters.vcenters,
      defaultParameters
    ]);
  };

  const deleteParameterGroup = (index): void => {
    setFieldValue(
      'parameters.vcenters',
      remove(index, 1, values.parameters.vcenters)
    );
  };

  const getFieldType = (name): string =>
    includes(name, [ParameterKeys.username, ParameterKeys.password])
      ? 'password'
      : 'text';

  const changeParameterValue = (index) => (event) => {
    setFieldValue(
      `parameters.vcenters.${index}.${event.target.name}`,
      event.target.value
    );
  };

  return {
    addParameterGroup,
    changeParameterValue,
    deleteParameterGroup,
    getError,
    getFieldType,
    onBlur,
    parameters: values.parameters.vcenters
  };
};

export default useParameters;
