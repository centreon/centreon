import { equals } from 'ramda';

import { useTheme } from '@mui/material';

import { MemoizedListing, SeverityCode } from '@centreon/ui';

import { CommonWidgetProps, Resource, SortOrder } from '../../../models';
import { PanelOptions } from '../models';

import { rowColorConditions } from './colors';
import useListing from './useListing';
import { defaultSelectedColumnIds } from './Columns';
import { DisplayType } from './models';
import DisplayTypeComponent from './DisplayType';

interface ListingProps
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    'dashboardId' | 'id' | 'playlistHash'
  > {
  changeViewMode?: (displayType) => void;
  displayType?: DisplayType;
  isFromPreview?: boolean;
  limit?: number;
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resources: Array<Resource>;
  selectedColumnIds?: Array<string>;
  setPanelOptions?: (partialOptions: object) => void;
  sortField?: string;
  sortOrder?: SortOrder;
  states: Array<string>;
  statuses: Array<string>;
}

const Listing = ({
  displayType = DisplayType.All,
  refreshCount,
  refreshIntervalToUse,
  resources,
  states,
  statuses,
  setPanelOptions,
  limit,
  selectedColumnIds,
  sortField,
  sortOrder,
  changeViewMode,
  isFromPreview,
  playlistHash,
  dashboardId,
  id
}: ListingProps): JSX.Element => {
  const theme = useTheme();

  const {
    selectColumns,
    resetColumns,
    changeSort,
    changeLimit,
    changePage,
    columns,
    page,
    isLoading,
    data,
    goToResourceStatusPage
  } = useListing({
    changeViewMode,
    dashboardId,
    displayType,
    id,
    isFromPreview,
    limit,
    playlistHash,
    refreshCount,
    refreshIntervalToUse,
    resources,
    setPanelOptions,
    sortField,
    sortOrder,
    states,
    statuses
  });

  return (
    <MemoizedListing
      columnConfiguration={{
        selectedColumnIds: selectedColumnIds || defaultSelectedColumnIds,
        sortable: true
      }}
      columns={columns}
      currentPage={(page || 1) - 1}
      getHighlightRowCondition={({ status }): boolean =>
        equals(status?.severity_code, SeverityCode.High)
      }
      limit={limit}
      loading={isLoading}
      memoProps={[
        data,
        sortField,
        sortOrder,
        page,
        isLoading,
        columns,
        displayType
      ]}
      rowColorConditions={rowColorConditions(theme)}
      rows={data?.result}
      sortField={sortField}
      sortOrder={sortOrder}
      subItems={{
        canCheckSubItems: true,
        enable: true,
        getRowProperty: (): string => 'parent_resource',
        labelCollapse: 'Collapse',
        labelExpand: 'Expand'
      }}
      totalRows={data?.meta?.total}
      visualizationActions={
        <DisplayTypeComponent
          displayType={displayType}
          setPanelOptions={setPanelOptions}
        />
      }
      onLimitChange={changeLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      onRowClick={goToResourceStatusPage}
      onSelectColumns={selectColumns}
      onSort={changeSort}
    />
  );
};

export default Listing;
