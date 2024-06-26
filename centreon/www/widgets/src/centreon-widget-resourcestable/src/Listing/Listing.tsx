import { equals } from 'ramda';

import { useTheme } from '@mui/material';

import { MemoizedListing, SeverityCode } from '@centreon/ui';

import { CommonWidgetProps, Resource, SortOrder } from '../../../models';
import { PanelOptions } from '../models';

import { rowColorConditions } from './colors';
import useListing from './useListing';
import { defaultSelectedColumnIds } from './Columns';
import { DisplayType as DisplayTypeEnum } from './models';
import DisplayType from './DisplayType';
import AcknowledgeForm from './Actions/Acknowledge';
import DowntimeForm from './Actions/Downtime';
import Actions from './Actions/Actions';

interface ListingProps
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    'dashboardId' | 'id' | 'playlistHash'
  > {
  changeViewMode?: (displayType) => void;
  displayType?: DisplayTypeEnum;
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
  widgetPrefixQuery: string;
}

const Listing = ({
  displayType = DisplayTypeEnum.All,
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
  id,
  widgetPrefixQuery
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
    goToResourceStatusPage,
    hasMetaService,
    selectedResources,
    setSelectedResources,
    resourcesToAcknowledge,
    resourcesToSetDowntime,
    cancelAcknowledge,
    confirmAcknowledge,
    cancelSetDowntime,
    confirmSetDowntime
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
    statuses,
    widgetPrefixQuery
  });

  return (
    <>
      <MemoizedListing
        checkable
        actions={<Actions />}
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
          displayType,
          selectedResources
        ]}
        rowColorConditions={rowColorConditions(theme)}
        rows={data?.result}
        selectedRows={selectedResources}
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
          <DisplayType
            displayType={displayType}
            hasMetaService={hasMetaService}
            setPanelOptions={setPanelOptions}
          />
        }
        onLimitChange={changeLimit}
        onPaginate={changePage}
        onResetColumns={resetColumns}
        onRowClick={goToResourceStatusPage}
        onSelectColumns={selectColumns}
        onSelectRows={setSelectedResources}
        onSort={changeSort}
      />
      {resourcesToAcknowledge.length > 0 && (
        <AcknowledgeForm
          resources={resourcesToAcknowledge}
          onClose={cancelAcknowledge}
          onSuccess={confirmAcknowledge}
        />
      )}
      {resourcesToSetDowntime.length > 0 && (
        <DowntimeForm
          resources={resourcesToSetDowntime}
          onClose={cancelSetDowntime}
          onSuccess={confirmSetDowntime}
        />
      )}
    </>
  );
};

export default Listing;
