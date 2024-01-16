import { equals } from 'ramda';

import { useTheme } from '@mui/material';

import { MemoizedListing, SeverityCode } from '@centreon/ui';

import { rowColorConditions } from './colors';
import useListing from './useListing';

interface ListingProps {
  displayType;
  refreshCount;
  refreshIntervalToUse;
  resources;
  states;
  statuses;
}

const Listing = ({
  displayType,
  refreshCount,
  refreshIntervalToUse,
  resources,
  states,
  statuses
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
    selectedColumnIds,
    page,
    sortField,
    sortOrder,
    isLoading,
    data
  } = useListing({
    displayType,
    refreshCount,
    refreshIntervalToUse,
    resources,
    states,
    statuses
  });

  return (
    <MemoizedListing
      columnConfiguration={{
        selectedColumnIds,
        sortable: areColumnsSortable
      }}
      columns={columns}
      currentPage={(page || 1) - 1}
      getHighlightRowCondition={({ status }): boolean =>
        equals(status?.severity_code, SeverityCode.High)
      }
      limit={data?.meta?.limit}
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
