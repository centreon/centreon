import { equals } from 'ramda';

import { useFetchQuery } from '@centreon/ui';
import { useSetAtom } from 'jotai';
import { useSearchParams } from 'react-router';
import { modalStateAtom } from '../../ConfigurationBase/Modal/atoms';
import { hostGroupDecoder } from '../api/decoders';
import { getHostGroupEndpoint } from '../api/endpoints';
import { HostGroupItem } from '../models';
import { modalInitialState } from '../utils';

interface InitialValuesState {
  initialValues: HostGroupItem;
  isLoading: boolean;
}

const defaultInitialValues = {
  name: '',
  alias: '',
  comment: '',
  geoCoords: '',
  hosts: [],
  resourceAccessRules: []
};

const useFormInitialValues = ({ mode, id }): InitialValuesState => {
  const setModalState = useSetAtom(modalStateAtom);
  const [, setSearchParams] = useSearchParams();

  const { data, isLoading: loading } = useFetchQuery<HostGroupItem>({
    decoder: hostGroupDecoder,
    getEndpoint: () => getHostGroupEndpoint({ id }),
    getQueryKey: () => ['hostGroup', id],
    queryOptions: {
      enabled: equals(mode, 'edit'),
      suspense: false
    },
    catchError: () => {
      setModalState(modalInitialState);
      setSearchParams({});
    }
  });

  const initialValues =
    data && equals(mode, 'edit') ? data : defaultInitialValues;

  const isLoading = equals(mode, 'edit') ? loading : false;

  return {
    initialValues: {
      ...initialValues,
      hosts: [{ id: 1, name: 'Host_1' }],
      resourceAccessRules: [
        { id: 1, name: 'Rule_1' },
        { id: 2, name: 'Rule_2' }
      ]
    },
    isLoading
  };
};

export default useFormInitialValues;
