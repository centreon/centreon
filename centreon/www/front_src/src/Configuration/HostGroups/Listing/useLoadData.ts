import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { useGetAll } from './api';
import {
  filtersAtom,
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom
} from './atoms';
import { HostGroupListItem, List } from './models';

interface LoadDataState {
  data?: List<HostGroupListItem>;
  isLoading: boolean;
}

const useLoadData = (): LoadDataState => {
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const filters = useAtomValue(filtersAtom);

  const searchConditions = [
    {
      field: 'name',
      values: { $rg: filters.name }
    },
    {
      field: 'alias',
      values: { $rg: filters.alias }
    },
    ...(equals(filters.enabled, filters.disabled)
      ? []
      : [
          {
            field: 'is_activated',
            values: { $eq: filters.enabled }
          }
        ])
  ];

  const { data, isLoading } = useGetAll({
    sortField,
    sortOrder,
    page,
    limit,
    searchConditions
  });

  return { data, isLoading };
};

export default useLoadData;
