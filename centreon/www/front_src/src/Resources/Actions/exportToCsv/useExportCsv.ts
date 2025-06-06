import { buildListingEndpoint, useFetchQuery, useSnackbar } from '@centreon/ui';
import { refreshIntervalAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { getColumns as getAllColumns } from '../../Listing/columns';
import { listingAtom, selectedColumnIdsAtom } from '../../Listing/listingAtoms';
import useGetCriteriaName from '../../Listing/useLoadResources/useGetCriteriaName';
import { getSearch } from '../../Listing/useLoadResources/utils';
import { countResourcesEndpoint } from '../../api/endpoint';
import { labelExportProcessingInProgress } from '../../translatedLabels';
import { selectedVisualizationAtom } from '../actionsAtoms';
import { csvExportEndpoint } from '../api/endpoint';
import { Count, ListSearch } from './models';

export const maxResources = 10000;
const unauthorizedColumn = 'graph';

interface UseExportCsvProps {
  isAllPagesChecked: boolean;
  isAllColumnsChecked: boolean;
  isOpen: boolean;
}

interface UseExportCsv {
  exportCsv: () => void;
  isLoading: boolean;
  hasReachedMaximumLinesToExport: boolean;
  numberExportedLines: number;
}

const useExportCsv = ({
  isAllColumnsChecked,
  isAllPagesChecked,
  isOpen
}: UseExportCsvProps): UseExportCsv => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const { getCriteriaNames, getCriteriaValue, getCriteriaIds } =
    useGetCriteriaName();
  const visualization = useAtomValue(selectedVisualizationAtom);
  const selectedColumnIds = useAtomValue(selectedColumnIdsAtom);
  const refreshInterval = useAtomValue(refreshIntervalAtom);

  const listing = useAtomValue(listingAtom);

  const getListSearch = ({ array, field }: ListSearch) => {
    return array.map((name) => ({
      field,
      values: {
        $rg: name
      }
    }));
  };

  const columns = useMemo(() => {
    const allColumns = getAllColumns({
      actions: {},
      t,
      visualization
    });
    const orderedColumns = new Set([
      ...selectedColumnIds.filter((item) => !equals(item, unauthorizedColumn)),
      ...allColumns
        .map(({ id }) => id)
        .filter((item) => !equals(item, unauthorizedColumn))
    ]);

    return [...orderedColumns];
  }, [selectedColumnIds]);

  const getCurrentFilterParameters = () => {
    const names = getCriteriaNames('names');
    const parentNames = getCriteriaNames('parent_names');
    const queryParameters = [
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
        value: getCriteriaNames('service_severities')
      },
      {
        name: 'host_severity_names',
        value: getCriteriaNames('host_severities')
      }
    ];

    const filtersParameters = {
      search: {
        ...(getSearch({ searchCriteria: getCriteriaValue('search') }) ?? {}),
        conditions: [
          ...getListSearch({ array: names, field: 'name' }),
          ...getListSearch({ array: parentNames, field: 'parent_name' })
        ]
      }
    };

    return { filtersParameters, queryParameters };
  };

  const getColumns = () => {
    if (isAllColumnsChecked) {
      return columns.map((column) => `columns[]=${column}`).join('&');
    }

    const filteredColumns = selectedColumnIds?.filter(
      (item) => !equals(item, unauthorizedColumn)
    );

    return filteredColumns.map((column) => `columns[]=${column}`).join('&');
  };

  const getParameters = (includePagination = false) => {
    const { filtersParameters, queryParameters } = getCurrentFilterParameters();
    const sort = getCriteriaValue('sort');

    const paginationParameters = includePagination
      ? {
          page: listing?.meta?.page || 1,
          limit: listing?.meta?.limit || 10,
          sort: {
            [sort?.[0] as string]: sort?.[1] || '',
            last_status_change: 'desc'
          }
        }
      : {};

    const types = getCriteriaIds('resource_types');
    const statuses = getCriteriaIds('statuses');

    const parameters = {
      ...filtersParameters,
      ...paginationParameters
    };

    return {
      parameters,
      customQueryParameters: [
        ...queryParameters,
        { name: 'types', value: types },
        {
          name: 'statuses',
          value: statuses?.map((status) => status.toUpperCase())
        }
      ]
    };
  };

  const getEndpoint = ({ baseEndpoint, includePagination = false }): string => {
    const { parameters, customQueryParameters } =
      getParameters(includePagination);

    return buildListingEndpoint({
      parameters,
      baseEndpoint,
      customQueryParameters: [
        ...customQueryParameters,
        { name: 'all_pages', value: isAllPagesChecked }
      ]
    });
  };

  const { data, isLoading } = useFetchQuery<Count>({
    getEndpoint: () =>
      getEndpoint({
        baseEndpoint: countResourcesEndpoint,
        includePagination: !isAllPagesChecked
      }),
    getQueryKey: () => [
      'exportedLines',
      getEndpoint({
        baseEndpoint: countResourcesEndpoint,
        includePagination: !isAllPagesChecked
      }),
      isAllPagesChecked
    ],
    queryOptions: {
      enabled: isOpen,
      suspense: false,
      refetchInterval: refreshInterval * 1000,
      gcTime: 0,
      staleTime: 0
    }
  });

  const numberExportedLines = data?.count || 0;

  const hasReachedMaximumLinesToExport = numberExportedLines > maxResources;

  const exportCsv = () => {
    showSuccessMessage(t(labelExportProcessingInProgress));

    const { parameters, customQueryParameters } = getParameters(true);

    const endpoint = buildListingEndpoint({
      parameters,
      baseEndpoint: csvExportEndpoint,
      customQueryParameters: [
        ...customQueryParameters,
        { name: 'all_pages', value: isAllPagesChecked },
        { name: 'max_lines', value: maxResources }
      ]
    });

    window.open(
      `${endpoint}&${getColumns()}&format=csv`,
      'noopener',
      'noreferrer'
    );
  };

  return {
    exportCsv,
    hasReachedMaximumLinesToExport,
    numberExportedLines,
    isLoading
  };
};

export default useExportCsv;
