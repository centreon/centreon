import { useAtomValue } from 'jotai';
import { equals, isNotEmpty, isNotNil, pluck } from 'ramda';

import { useGetAll } from '../api';
import { limitAtom, pageAtom, sortFieldAtom, sortOrderAtom } from './atoms';

import { useMemo } from 'react';
import { FieldType } from '../../models';
import { configurationAtom, filtersAtom } from '../atoms';

interface LoadDataState {
  data?;
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
      (filter) => equals(filter.fieldType, FieldType.Status)
    );

    const statusCondition =
      hasStatusFilter && !equals(filters?.enabled, filters?.disabled)
        ? [{ field: 'is_activated', values: { $eq: filters.enabled } }]
        : [];

    const otherConditions = configuration?.filtersConfiguration?.reduce(
      (acc, filter) => {
        if (equals(filter.fieldType, FieldType.Status)) return acc;

        const fieldName = filter.fieldName as string;
        const filterValue = filters[fieldName];

        if (
          equals(filter.fieldType, FieldType.MultiAutocomplete) ||
          equals(filter.fieldType, FieldType.MultiConnectedAutocomplete)
        ) {
          return isNotNil(filterValue) && isNotEmpty(filterValue)
            ? [
                ...acc,
                {
                  field: fieldName,
                  values: { $in: pluck('id', filterValue) }
                }
              ]
            : acc;
        }

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
