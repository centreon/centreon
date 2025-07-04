import { useFormikContext } from 'formik';
import { remove } from 'ramda';

import { AdditionalConnectorConfiguration, Parameter } from '../../models';
import { getDefaultParameters } from '../../utils';

interface UseParameterStates {
  addParameterGroup: (index: number) => () => void;
  deleteParameterGroup: (index) => void;
  parameters: Array<Parameter>;
}

const useParameters = (): UseParameterStates => {
  const { values, setFieldValue } =
    useFormikContext<AdditionalConnectorConfiguration>();

  const addParameterGroup = (index: number) => (): void => {
    setFieldValue('parameters.vcenters', [
      ...values.parameters.vcenters,
      getDefaultParameters(index)
    ]);
  };

  const deleteParameterGroup = (index): void => {
    setFieldValue(
      'parameters.vcenters',
      remove(index, 1, values.parameters.vcenters)
    );
  };

  return {
    addParameterGroup,
    deleteParameterGroup,
    parameters: values.parameters.vcenters
  };
};

export default useParameters;
