import { useAtomValue } from 'jotai';
import { buildListingEndpoint, useSnackbar } from 'packages/ui/src';
import { equals } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { getColumns as getAllColumns } from '../../Listing/columns';
import { listingAtom, selectedColumnIdsAtom } from '../../Listing/listingAtoms';
import useGetCriteriaName from '../../Listing/useLoadResources/useGetCriteriaName';
import { getSearch } from '../../Listing/useLoadResources/utils';
import { labelExportProcessingInProgress } from '../../translatedLabels';
import { selectedVisualizationAtom } from '../actionsAtoms';
import { ListSearch } from './models';

const unauthorizedColumn = 'graph';
interface Parameters {
  isAllPagesChecked: boolean;
  isAllColumnsChecked: boolean;
}

const useExportCsv = ({
  isAllColumnsChecked,
  isAllPagesChecked
}: Parameters): (() => void) => {
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

  const getPages = () => {
    if (isAllPagesChecked) {
      return { total: listing?.meta?.total };
    }

    return { page: listing?.meta?.page, limit: listing?.meta?.limit };
  };

  const exportCsv = () => {
    showSuccessMessage(t(labelExportProcessingInProgress));
    const { filtersParameters, queryParameters } = getCurrentFilterParameters();

    const parameters = { ...filtersParameters, ...getPages() };
    const customQueryParameters = [
      ...queryParameters,
      { name: 'columns', value: getColumns() }
    ];

    const endpoint = buildListingEndpoint({
      parameters,
      baseEndpoint: 'test',
      customQueryParameters
    });

    window.open(endpoint);
  };

  return exportCsv;
};

export default useExportCsv;
