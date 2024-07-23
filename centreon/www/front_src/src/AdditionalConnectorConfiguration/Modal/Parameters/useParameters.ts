import { useFormikContext } from 'formik';
import { includes, remove } from 'ramda';

import { ConnectorConfiguration, Parameter, ParameterKeys } from '../models';
import { defaultParameters } from '../utils';

interface UsParameters {
  addParameterGroup: () => void;
  changeParameterValue: (index: number) => (event) => void;
  deleteParameterGroup: (index) => void;
  getFieldType: (name) => string;
  isAddButtonDisabled: boolean;
  parameters: Array<Parameter>;
}

const useParameters = (): UsParameters => {
  const { values, setFieldValue } = useFormikContext<ConnectorConfiguration>();

  const addParameterGroup = (): void => {
    setFieldValue('parameters', [...values.parameters, defaultParameters]);
  };

  const isAddButtonDisabled = false;

  const deleteParameterGroup = (index): void => {
    setFieldValue('parameters', remove(index, 1, values.parameters));
  };

  const getFieldType = (name): string =>
    includes(name, [ParameterKeys.username, ParameterKeys.password])
      ? 'password'
      : 'text';

  const changeParameterValue = (index) => (event) => {
    setFieldValue(
      `parameters.${index}.${event.target.name}`,
      event.target.value
    );
  };

  return {
    addParameterGroup,
    changeParameterValue,
    deleteParameterGroup,
    getFieldType,
    isAddButtonDisabled,
    parameters: values.parameters
  };
};

export default useParameters;
