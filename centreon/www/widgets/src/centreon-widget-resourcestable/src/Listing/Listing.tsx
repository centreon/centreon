import { equals } from 'ramda';

import { useTheme } from '@mui/material';

import { MemoizedListing, SeverityCode } from '@centreon/ui';

import { rowColorConditions } from './colors';
import useListing from './useListing';
import {
  defaultSelectedColumnIds,
  defaultSelectedColumnIdsforViewByHost
} from './Columns';

interface ListingProps {
  displayType;
  limit;
  refreshCount;
  refreshIntervalToUse;
  resources;
  selectedColumnIds;
  setPanelOptions;
  states;
  statuses;
}

const Listing = ({
  displayType,
  refreshCount,
  refreshIntervalToUse,
  resources,
  states,
  statuses,
  setPanelOptions,
  limit,
  selectedColumnIds
}: ListingProps): JSX.Element => {
  const theme = useTheme();

  const {
    areColumnsSortable,
    selectColumns,
    resetColumns,
    changeSort,
    changeLimit,
    changePage,
    columns,
    page,
    sortField,
    sortOrder,
    isLoading,
    data
  } = useListing({
    displayType,
    limit,
    refreshCount,
    refreshIntervalToUse,
    resources,
    setPanelOptions,
    states,
    statuses
  });

  const initialSelectedColumnIds = equals(displayType, 'host')
    ? defaultSelectedColumnIdsforViewByHost
    : defaultSelectedColumnIds;

  return (
    <MemoizedListing
      columnConfiguration={{
        selectedColumnIds: selectedColumnIds || initialSelectedColumnIds,
        sortable: areColumnsSortable
      }}
      columns={columns}
      currentPage={(page || 1) - 1}
      getHighlightRowCondition={({ status }): boolean =>
        equals(status?.severity_code, SeverityCode.High)
      }
      limit={limit}
      loading={isLoading}
      memoProps={[data, sortField, sortOrder, page, isLoading, columns]}
      rowColorConditions={rowColorConditions(theme)}
      rows={data?.result}
      sortField={sortField}
      sortOrder={sortOrder}
      subItems={{
        canCheckSubItems: true,
        enable: true,
        getRowProperty: (): string => 'children',
        labelCollapse: 'Collapse',
        labelExpand: 'Expand'
      }}
      totalRows={data?.meta?.total}
      onLimitChange={changeLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      onSelectColumns={selectColumns}
      onSort={changeSort}
    />
  );
};

export default Listing;
