import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { useGetAll } from '../api';
import { limitAtom, pageAtom, sortFieldAtom, sortOrderAtom } from './atoms';
import { HostGroupListItem, List } from './models';

import { useMemo } from 'react';
import { configurationAtom, filtersAtom } from '../../atoms';
import { FieldType } from '../../models';

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
  const configuration = useAtomValue(configurationAtom);

  const searchConditions = useMemo(() => {
    const hasStatusFilter = configuration?.filtersConfiguration?.some(
      (filter) => filter.fieldType === FieldType.Status
    );

    const statusCondition =
      hasStatusFilter && !equals(filters?.enabled, filters?.disabled)
        ? [{ field: 'is_activated', values: { $eq: filters.enabled } }]
        : [];

    const otherConditions = configuration?.filtersConfiguration?.reduce(
      (acc, filter) => {
        if (filter.fieldType === FieldType.Status) return acc;

        const fieldName = filter.fieldName;
        const filterValue = filters[fieldName];

        return filterValue
          ? [...acc, { field: fieldName, values: { $rg: filterValue } }]
          : acc;
      },
      [] as Array<{ field: string; values: object }>
    );

    return [...statusCondition, ...(otherConditions || [])];
  }, [configuration?.filtersConfiguration, filters]);

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
