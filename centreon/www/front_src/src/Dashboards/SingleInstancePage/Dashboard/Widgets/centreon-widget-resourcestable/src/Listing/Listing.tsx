import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { ReactElement } from 'react';

import { useTheme } from '@mui/material';

import { MemoizedListing, SeverityCode } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { CommonWidgetProps, Resource, SortOrder } from '../../../models';
import { PanelOptions } from '../models';

import { openTicketAtom } from '../atom';
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
  changeViewMode?: (displayType: DisplayTypeEnum) => void;
  displayType?: DisplayTypeEnum;
  hostSeverities: Array<NamedEntity>;
  isFromPreview?: boolean;
  limit?: number;
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
  serviceSeverities
}: ListingProps): ReactElement => {
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
    displayType,
    hostSeverities,
    id,
    isFromPreview,
    limit,
    playlistHash,
    refreshCount,
    refreshIntervalToUse,
    resources,
    serviceSeverities,
    setPanelOptions,
    sortField,
    sortOrder,
    states,
    statuses,
    statusTypes,
    widgetPrefixQuery
  });

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);
  const { isOpenTicketEnabled, provider } = useAtomValue(openTicketAtom);

  return (
    <>
      <MemoizedListing
        actions={
          <Actions
            displayType={displayType}
            hasMetaService={hasMetaService}
            isOpenTicketEnabled={isOpenTicketEnabled}
            setPanelOptions={setPanelOptions}
          />
        }
        actionsBarMemoProps={[displayType, hasMetaService, isOpenTicketEnabled]}
        checkable
        columnConfiguration={{
          selectedColumnIds: selectedColumnIds || defaultSelectedColumnIds,
          sortable: true
        }}
        columns={columns}
        currentPage={(page || 1) - 1}
        getHighlightRowCondition={({ status }): boolean =>
          equals(status?.severity_code, SeverityCode.High)
        }
        isActionBarVisible={!isOnPublicPage}
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
        onLimitChange={changeLimit}
        onPaginate={changePage}
        onResetColumns={resetColumns}
        onRowClick={goToResourceStatusPage}
        onSelectColumns={selectColumns}
        onSelectRows={setSelectedResources}
        onSort={changeSort}
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
      />
      {resourcesToAcknowledge.length > 0 && (
        <AcknowledgeForm
          onClose={cancelAcknowledge}
          onSuccess={confirmAcknowledge}
          resources={resourcesToAcknowledge}
        />
      )}
      {resourcesToSetDowntime.length > 0 && (
        <DowntimeForm
          onClose={cancelSetDowntime}
          onSuccess={confirmSetDowntime}
          resources={resourcesToSetDowntime}
        />
      )}

      {resourcesToOpenTicket.length > 0 && (
        <OpenTicketModal
          close={onTicketClose}
          isOpen
          providerID={provider?.id}
          resource={resourcesToOpenTicket[0]}
        />
      )}
      <CloseTicketModal providerID={provider?.id} />
    </>
  );
};

export default Listing;
