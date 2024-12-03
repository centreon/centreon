import { useAtomValue } from 'jotai';
import {
  SelectEntry,
  buildListingEndpoint,
  useSnackbar
} from 'packages/ui/src';
import { equals, prop } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { getCriteriaValueDerivedAtom } from '../../Filter/filterAtoms';
import { getColumns } from '../../Listing/columns';
import { listingAtom, selectedColumnIdsAtom } from '../../Listing/listingAtoms';
import { getSearch } from '../../Listing/useLoadResources/utils';
import {} from '../../translatedLabels';
import { selectedVisualizationAtom } from '../actionsAtoms';

interface ListSearch {
  array: Array<string>;
  field: string;
}

const useExportCSV = ({ allPages, allColumns }) => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const visualization = useAtomValue(selectedVisualizationAtom);
  const selectedColumnIds = useAtomValue(selectedColumnIdsAtom);
  const listing = useAtomValue(listingAtom);
  const getCriteriaValue = useAtomValue(getCriteriaValueDerivedAtom);

  const unauthorizedColumn = 'graph';

  const getCriteriaNames = (name: string): Array<string> => {
    const criteriaValue = getCriteriaValue(name) as
      | Array<SelectEntry>
      | undefined;

    return (criteriaValue || []).map(prop('name')) as Array<string>;
  };

  const getListSearch = ({ array, field }: ListSearch) => {
    return array.map((name) => ({
      field,
      values: {
        $rg: name
      }
    }));
  };

  const columns = useMemo(() => {
    return getColumns({
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
        ...getSearch({ searchCriteria: getCriteriaValue('search') }),
        conditions: [
          ...getListSearch({ array: names, field: 'name' }),
          ...getListSearch({ array: parentNames, field: 'parent_name' })
        ]
      }
    };

    return { filtersParameters, queryParameters };
  };

  const getColumnsId = () => {
    if (allColumns) {
      const authorizedColumns = columns?.filter(
        ({ id }) => !equals(id, unauthorizedColumn)
      );
      return authorizedColumns?.map(({ id }) => id);
    }

    return selectedColumnIds?.filter(
      ({ id }) => !equals(id, unauthorizedColumn)
    );
  };

  const getPages = () => {
    if (allPages) {
      return { total: listing?.meta?.total };
    }

    return { page: listing?.meta?.page, limit: listing?.meta?.limit };
  };

  const exportCsv = () => {
    showSuccessMessage('Export processing in progress');
    const { filtersParameters, queryParameters } = getCurrentFilterParameters();
    const parameters = { ...filtersParameters, ...getPages() };
    const customQueryParameters = {
      ...queryParameters,
      columnsId: getColumnsId()
    };

    const endpoint = buildListingEndpoint({
      parameters,
      baseEndpoint: 'test',
      customQueryParameters
    });

    // window.open(endpoint);
  };

  return exportCsv;
};

export default useExportCSV;
