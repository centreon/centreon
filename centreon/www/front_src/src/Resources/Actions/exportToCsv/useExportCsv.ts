import { buildListingEndpoint, useFetchQuery, useSnackbar } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { getColumns as getAllColumns } from '../../Listing/columns';
import { listingAtom, selectedColumnIdsAtom } from '../../Listing/listingAtoms';
import useGetCriteriaName from '../../Listing/useLoadResources/useGetCriteriaName';
import { getSearch } from '../../Listing/useLoadResources/utils';
import { resourcesEndpoint } from '../../api/endpoint';
import { labelExportProcessingInProgress } from '../../translatedLabels';
import { selectedVisualizationAtom } from '../actionsAtoms';
import { csvExportEndpoint } from '../api/endpoint';
import { ListSearch } from './models';

const maxResources = 10000;
const unauthorizedColumn = 'graph';

interface Parameters {
  isAllPagesChecked: boolean;
  isAllColumnsChecked: boolean;
}

interface UseExportCsv {
  exportCsv: () => void;
  disableExport: boolean;
  numberExportedLines: string;
}

const useExportCsv = ({
  isAllColumnsChecked,
  isAllPagesChecked
}: Parameters): UseExportCsv => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const { getCriteriaNames, getCriteriaValue } = useGetCriteriaName();
  const visualization = useAtomValue(selectedVisualizationAtom);
  const selectedColumnIds = useAtomValue(selectedColumnIdsAtom);

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
    return getAllColumns({
      actions: {},
      t,
      visualization
    });
  }, []);

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
      const authorizedColumns = columns?.filter(
        ({ id }) => !equals(id, unauthorizedColumn)
      );
      return authorizedColumns?.map(({ id }) => id);
    }

    return selectedColumnIds?.filter(
      (item) => !equals(item, unauthorizedColumn)
    );
  };

  const getParameters = () => {
    const { filtersParameters, queryParameters } = getCurrentFilterParameters();

    const paginationParameters = {
      page: listing?.meta?.page || 1,
      limit: listing?.meta?.limit || 10
    };

    const parameters = { ...filtersParameters, ...paginationParameters };

    return { parameters, customQueryParameters: [...queryParameters] };
  };

  const getEndpoint = (baseEndpoint: string): string => {
    const { parameters, customQueryParameters } = getParameters();

    return buildListingEndpoint({
      parameters,
      baseEndpoint,
      customQueryParameters
    });
  };

  const { data } = useFetchQuery({
    getEndpoint: () => getEndpoint(resourcesEndpoint),
    getQueryKey: () => ['exportedLines', getEndpoint(resourcesEndpoint)],
    queryOptions: {
      suspense: false
    }
  });

  const filteredCurrentLines = `${data?.result?.length} / ${maxResources}`;
  const currentLines = `${data?.meta?.total} / ${maxResources}`;

  const numberExportedLines = isAllPagesChecked
    ? currentLines
    : filteredCurrentLines;

  const disableExport = isAllPagesChecked
    ? data?.meta?.total > maxResources
    : data?.result?.length > maxResources;

  const exportCsv = () => {
    showSuccessMessage(t(labelExportProcessingInProgress));

    const { parameters, customQueryParameters } = getParameters();

    const endpoint = buildListingEndpoint({
      parameters,
      baseEndpoint: csvExportEndpoint,
      customQueryParameters: [
        ...customQueryParameters,
        { name: 'format', value: 'csv' },
        { name: 'columns', value: getColumns() },
        { name: 'all_pages', value: isAllPagesChecked }
      ]
    });

    window.open(endpoint, 'noopener', 'noreferrer');
  };

  return { exportCsv, disableExport, numberExportedLines };
};

export default useExportCsv;
