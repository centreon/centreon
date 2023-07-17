import {
  Dispatch,
  SetStateAction,
  useCallback,
  useMemo,
  useState
} from 'react';

import { filter, includes } from 'ramda';

import useFederatedWidgets from '../../../federatedModules/useFederatedWidgets';

import { FederatedModule } from 'www/front_src/src/federatedModules/models';

interface UseSearchWidgetsState {
  filteredWidgets: Array<FederatedModule>;
  search: string;
  setSearch: Dispatch<SetStateAction<string>>;
}

const useSearchWidgets = (): UseSearchWidgetsState => {
  const [search, setSearch] = useState('');

  const { federatedWidgets } = useFederatedWidgets();

  const includesSearch = useCallback(includes(search), [search]);

  const filteredWidgets = useMemo(
    () =>
      filter(
        ({ moduleName }) => includesSearch(moduleName),
        federatedWidgets || []
      ),
    [search, federatedWidgets]
  );

  return {
    filteredWidgets,
    search,
    setSearch
  };
};

export default useSearchWidgets;
