import { useAtomValue } from 'jotai';
import { equals, isEmpty, isNil, mergeRight, not, prop } from 'ramda';

import type { SelectEntry } from '@centreon/ui';
import {
  getFoundFields,
  useFetchQuery,
  buildListingEndpoint
} from '@centreon/ui';

import { searchableFields } from '../../Filter/Criterias/searchQueryLanguage';
import { getCriteriaValueDerivedAtom } from '../../Filter/filterAtoms';
import { SortOrder } from '../../models';
import { Search } from '../../Listing/useLoadResources/models';
import { exportToCSVEndpoint } from '../../api/endpoint';

export interface LoadResources {
  loading: boolean;
  submit: () => void;
}

const secondSortField = 'last_status_change';
const defaultSecondSortCriteria = { [secondSortField]: SortOrder.desc };

interface Props {
  columns: Array<string>;
  limit?: number;
  page?: number;
}

const useExportToCSVRequest = ({
  columns,
  page,
  limit
}: Props): LoadResources => {
  const getCriteriaValue = useAtomValue(getCriteriaValueDerivedAtom);

  const getSort = (): { [sortField: string]: SortOrder } | undefined => {
    const sort = getCriteriaValue('sort');

    if (isNil(sort)) {
      return undefined;
    }

    const [sortField, sortOrder] = sort as [string, SortOrder];

    const secondSortCriteria =
      not(equals(sortField, secondSortField)) && defaultSecondSortCriteria;

    return {
      [sortField]: sortOrder,
      ...secondSortCriteria
    };
  };

  const getSearch = (): Search | undefined => {
    const searchCriteria = getCriteriaValue('search');

    if (!searchCriteria) {
      return undefined;
    }

    const fieldMatches = getFoundFields({
      fields: searchableFields,
      value: searchCriteria as string
    });

    if (!isEmpty(fieldMatches)) {
      const matches = fieldMatches.map((item) => {
        const field = item?.field;
        const values = item.value?.split(',')?.join('|');

        return { field, value: `${field}:${values}` };
      });

      const formattedValue = matches.reduce((accumulator, previousValue) => {
        return {
          ...accumulator,
          value: `${accumulator.value} ${previousValue.value}`
        };
      });

      return {
        regex: {
          fields: matches.map(({ field }) => field),
          value: formattedValue.value
        }
      };
    }

    return {
      regex: {
        fields: searchableFields,
        value: searchCriteria as string
      }
    };
  };

  const getCriteriaIds = (name: string): Array<string | number> | undefined => {
    const criteriaValue = getCriteriaValue(name) as
      | Array<SelectEntry>
      | undefined;

    return criteriaValue?.map(prop('id'));
  };

  const getCriteriaNames = (name: string): Array<string> => {
    const criteriaValue = getCriteriaValue(name) as
      | Array<SelectEntry>
      | undefined;

    return (criteriaValue || []).map(prop('name')) as Array<string>;
  };

  const getCriteriaLevels = (name: string): Array<number> => {
    const criteriaValue = getCriteriaValue(name) as
      | Array<SelectEntry>
      | undefined;

    const results = criteriaValue?.map(prop('name'));

    return results?.map((item) => Number(item)) as Array<number>;
  };

  const names = getCriteriaNames('names');
  const parentNames = getCriteriaNames('parent_names');

  const { isFetching, fetchQuery } = useFetchQuery({
    getEndpoint: () => {
      return buildListingEndpoint({
        baseEndpoint: exportToCSVEndpoint,
        customQueryParameters: [
          { name: 'columns', value: columns },
          { name: 'states', value: getCriteriaIds('states') },
          {
            name: 'status_types',
            value: getCriteriaIds('status_types')
          },
          { name: 'types', value: getCriteriaIds('resource_types') },
          { name: 'statuses', value: getCriteriaIds('statuses') },
          {
            name: 'host_category_names',
            value: getCriteriaNames('host_categories')
          },
          {
            name: 'service_category_names',
            value: getCriteriaNames('service_categories')
          },
          { name: 'hostgroup_names', value: getCriteriaNames('host_groups') },
          {
            name: 'servicegroup_names',
            value: getCriteriaNames('service_groups')
          },
          {
            name: 'monitoring_server_names',
            value: getCriteriaNames('monitoring_servers')
          },
          {
            name: 'service_severity_names',
            value: getCriteriaLevels('service_severity_levels')
          },
          {
            name: 'service_severity_levels',
            value: getCriteriaLevels('service_severity_levels')
          },
          {
            name: 'host_severity_names',
            value: getCriteriaNames('host_severities')
          },
          {
            name: 'host_severity_levels',
            value: getCriteriaLevels('host_severity_levels')
          }
        ],
        parameters: {
          limit,
          page,
          search: mergeRight(getSearch() || {}, {
            conditions: [
              ...names.map((name) => ({
                field: 'name',
                values: {
                  $rg: name
                }
              })),
              ...parentNames.map((name) => ({
                field: 'parent_name',
                values: {
                  $rg: name
                }
              }))
            ]
          }),
          sort: getSort()
        }
      });
    },
    getQueryKey: () => ['export-csv'],
    queryOptions: {
      enabled: false,
      refetchOnMount: false,
      suspense: false
    }
  });

  return { loading: isFetching, submit: fetchQuery };
};

export default useExportToCSVRequest;
