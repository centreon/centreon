import { useAtomValue } from 'jotai';
import { equals, isNotEmpty, isNotNil, pluck } from 'ramda';
import { useMemo } from 'react';

import { QueryParameter } from '@centreon/ui';
import { FieldType } from '../../models';
import { useGetAll } from '../api';
import { configurationAtom } from '../atoms';
import { limitAtom, pageAtom, sortFieldAtom, sortOrderAtom } from './atoms';

interface LoadDataState {
  data?;
  isLoading: boolean;
}

const useLoadData = ({ filtersAtom, filtersAtomKey }): LoadDataState => {
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const page = useAtomValue(pageAtom);
  const limit = useAtomValue(limitAtom);
  const filters = useAtomValue(filtersAtom);
  const configuration = useAtomValue(configurationAtom);

  const apiFormat = configuration?.api?.apiFormat;

  const isStatusFilterApplied =
    configuration?.filtersConfiguration?.some((filter) =>
      equals(filter.fieldType, FieldType.Status)
    ) && !equals(filters?.enabled, filters?.disabled);

  const getCheckboxQueries = () => {
    const isCheckboxFilterApplied = configuration?.filtersConfiguration?.some(
      (filter) => equals(filter.fieldType, FieldType.Checkbox)
    );

    if (!isCheckboxFilterApplied) {
      return [];
    }

    const filterName = configuration?.filtersConfiguration?.find((filter) =>
      equals(filter.fieldType, FieldType.Checkbox)
    )?.fieldName as string;

    const filterValue = filters?.[filterName];

    return filterValue
      ? [
          {
            name: filterName,
            value: true
          }
        ]
      : [];
  };

  const getCheckboxesQueries = () => {
    const isCheckboxesFilterApplied = configuration?.filtersConfiguration?.some(
      (filter) => equals(filter.fieldType, FieldType.Checkboxes)
    );

    if (!isCheckboxesFilterApplied) {
      return [];
    }

    const filterName = configuration?.filtersConfiguration?.find((filter) =>
      equals(filter.fieldType, FieldType.Checkboxes)
    )?.fieldName as string;

    const filterValues = filters?.[filterName];

    return filterValues.map((filterValue) => ({
      name: `${filterName}[eq]`,
      value: filterValue
    }));
  };

  const searchConditions = useMemo(() => {
    if (equals(apiFormat, 'JSON-LD')) {
      return [];
    }

    const statusCondition = isStatusFilterApplied
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

  const getCustomQueryParameters = (): Array<QueryParameter> => {
    if (!equals(apiFormat, 'JSON-LD')) {
      return [];
    }

    const statusQueryParam = isStatusFilterApplied
      ? [{ name: 'is_activated', value: filters?.enabled }]
      : [];

    const customQueryParameters = [
      { name: 'name[lk]', value: filters?.name },
      ...statusQueryParam,
      ...getCheckboxesQueries(),
      ...getCheckboxQueries()
    ];

    return customQueryParameters;
  };

  const { data, isLoading } = useGetAll({
    sortField,
    sortOrder,
    page,
    limit,
    searchConditions,
    filtersAtomKey,
    getCustomQueryParameters
  });

  return { data, isLoading };
};

export default useLoadData;
