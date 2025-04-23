import { useRefreshInterval } from '@centreon/ui';

import { Listing } from './Listing';
import { ResourcesTableProps } from './models';

const ResourceTable = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount,
  setPanelOptions,
  changeViewMode,
  isFromPreview,
  id,
  dashboardId,
  playlistHash,
  widgetPrefixQuery
}: Omit<ResourcesTableProps, 'store' | 'queryClient'>): JSX.Element => {
  const { resources } = panelData;

  const {
    displayType,
    refreshInterval,
    refreshIntervalCustom,
    states,
    statuses,
    limit,
    sortField,
    sortOrder,
    selectedColumnIds,
    statusTypes,
    hostSeverities,
    serviceSeverities,
    isDownHostHidden,
    isUnreachableHostHidden,
    displayResources,
    provider,
    isOpenTicketEnabled
  } = panelOptions;

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  return (
    <div style={{ height: '100%', width: '100%' }}>
      <Listing
        changeViewMode={changeViewMode}
        dashboardId={dashboardId}
        displayResources={displayResources}
        displayType={displayType}
        hostSeverities={hostSeverities}
        id={id}
        isDownHostHidden={isDownHostHidden}
        isFromPreview={isFromPreview}
        isOpenTicketEnabled={isOpenTicketEnabled}
        isUnreachableHostHidden={isUnreachableHostHidden}
        limit={limit}
        playlistHash={playlistHash}
        provider={provider}
        refreshCount={refreshCount}
        refreshIntervalToUse={refreshIntervalToUse}
        resources={resources}
        selectedColumnIds={selectedColumnIds}
        serviceSeverities={serviceSeverities}
        setPanelOptions={setPanelOptions}
        sortField={sortField}
        sortOrder={sortOrder}
        states={states}
        statusTypes={statusTypes}
        statuses={statuses}
        widgetPrefixQuery={widgetPrefixQuery}
      />
    </div>
  );
};

export default ResourceTable;
