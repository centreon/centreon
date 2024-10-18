import { equals } from 'ramda';

import { useTheme } from '@mui/material';

import { MemoizedListing, SeverityCode } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { CommonWidgetProps, Resource, SortOrder } from '../../../models';
import { PanelOptions } from '../models';

import { useAtomValue } from 'jotai';
import Actions from './Actions';
import AcknowledgeForm from './Actions/Acknowledge';
import DowntimeForm from './Actions/Downtime';
import CloseTicketModal from './Columns/CloseTicket/Modal';
import OpenTicketModal from './Columns/OpenTicket/Modal';
import { rowColorConditions } from './colors';
import { DisplayType as DisplayTypeEnum, NamedEntity } from './models';
import useListing from './useListing';

interface ListingProps
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    'dashboardId' | 'id' | 'playlistHash'
  > {
  changeViewMode?: (displayType) => void;
  displayResources: 'withTicket' | 'withoutTicket';
  displayType?: DisplayTypeEnum;
  hostSeverities: Array<NamedEntity>;
  isDownHostHidden: boolean;
  isFromPreview?: boolean;
  isOpenTicketEnabled: boolean;
  isUnreachableHostHidden: boolean;
  limit?: number;
  provider?: { id: number; name: string };
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resources: Array<Resource>;
  selectedColumnIds?: Array<string>;
  serviceSeverities: Array<NamedEntity>;
  setPanelOptions?: (partialOptions: object) => void;
  sortField?: string;
  sortOrder?: SortOrder;
  states: Array<string>;
  statusTypes: Array<'hard' | 'soft'>;
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
  widgetPrefixQuery,
  statusTypes,
  hostSeverities,
  serviceSeverities,
  isDownHostHidden,
  isUnreachableHostHidden,
  displayResources,
  provider,
  isOpenTicketEnabled
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
    confirmSetDowntime,
    defaultSelectedColumnIds,
    resourcesToOpenTicket,
    onTicketClose
  } = useListing({
    changeViewMode,
    dashboardId,
    displayResources,
    displayType,
    hostSeverities,
    id,
    isDownHostHidden,
    isFromPreview,
    isOpenTicketEnabled,
    isUnreachableHostHidden,
    limit,
    playlistHash,
    provider,
    refreshCount,
    refreshIntervalToUse,
    resources,
    serviceSeverities,
    setPanelOptions,
    sortField,
    sortOrder,
    states,
    statusTypes,
    statuses,
    widgetPrefixQuery
  });

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  return (
    <>
      <MemoizedListing
        paginated={!isOnPublicPage}
        checkable
        actions={
          isOnPublicPage ? undefined : (
            <Actions
              displayType={displayType}
              hasMetaService={hasMetaService}
              setPanelOptions={setPanelOptions}
              isOpenTicketEnabled={isOpenTicketEnabled}
            />
          )
        }
        actionsBarMemoProps={[
          displayType,
          hasMetaService,
          isOpenTicketEnabled,
          isOnPublicPage
        ]}
        columnConfiguration={{
          selectedColumnIds: isOnPublicPage
            ? undefined
            : selectedColumnIds || defaultSelectedColumnIds,
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

      {resourcesToOpenTicket.length > 0 && (
        <OpenTicketModal
          isOpen
          close={onTicketClose}
          providerID={provider?.id}
          resource={resourcesToOpenTicket[0]}
        />
      )}
      <CloseTicketModal providerID={provider?.id} />
    </>
  );
};

export default Listing;
