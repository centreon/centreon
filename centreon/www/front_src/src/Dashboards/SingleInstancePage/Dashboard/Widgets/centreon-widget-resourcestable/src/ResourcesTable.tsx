import { ReactElement } from 'react';

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
}: Omit<ResourcesTableProps, 'store' | 'queryClient'>): ReactElement => {
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
    serviceSeverities
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
        displayType={displayType}
        hostSeverities={hostSeverities}
        id={id}
        isFromPreview={isFromPreview}
        limit={limit}
        playlistHash={playlistHash}
        refreshCount={refreshCount}
        refreshIntervalToUse={refreshIntervalToUse}
        resources={resources}
        selectedColumnIds={selectedColumnIds}
        serviceSeverities={serviceSeverities}
        setPanelOptions={setPanelOptions}
        sortField={sortField}
        sortOrder={sortOrder}
        states={states}
        statuses={statuses}
        statusTypes={statusTypes}
        widgetPrefixQuery={widgetPrefixQuery}
      />
    </div>
  );
};

export default ResourceTable;
