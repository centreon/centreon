import { equals } from 'ramda';

import { additionalConnectorDecoder } from '../../api/decoders';
import { getAdditionalConnectorEndpoint } from '../../api/endpoints';
import { defaultParameters } from '../../utils';
import { AdditionalConnectorConfiguration } from '../models';

import { useFetchQuery } from '@centreon/ui';

interface InitialValuesState {
  initialValues: AdditionalConnectorConfiguration;
  isLoading: boolean;
}
const defaultInitialValues = {
  description: null,
  name: '',
  parameters: { port: 5700, vcenters: [defaultParameters] },
  pollers: [],
  type: 1
};

const formatInitialValues = (connector): AdditionalConnectorConfiguration => {
  const formattedConnector = {
    ...connector,
    type: 1
  };

  return formattedConnector;
};

const useFormInitialValues = ({ variant, id }): InitialValuesState => {
  const { data, isLoading: loading } = useFetchQuery({
    decoder: additionalConnectorDecoder,
    getEndpoint: () => getAdditionalConnectorEndpoint(id),
    getQueryKey: () => ['getOnACC', id],
    queryOptions: {
      enabled: equals(variant, 'update'),
      suspense: false
    }
  });

  const initialValues =
    data && equals(variant, 'update')
      ? formatInitialValues(data)
      : defaultInitialValues;

  const isLoading = equals(variant, 'update') ? loading : false;

  return { initialValues, isLoading };
};

export default useFormInitialValues;
