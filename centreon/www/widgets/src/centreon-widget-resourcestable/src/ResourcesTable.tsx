import { useRefreshInterval } from '@centreon/ui';

import { Listing } from './Listing';
import { ResourcesTableProps } from './models';

const ResourceTable = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount,
  setPanelOptions
}: ResourcesTableProps): JSX.Element => {
  const {
    displayType,
    refreshInterval,
    refreshIntervalCustom,
    states,
    statuses,
    limit,
    selectedColumnIds
  } = panelOptions;
  const { resources } = panelData;

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  return (
    <div style={{ height: '100%', width: '100%' }}>
      <Listing
        displayType={displayType}
        limit={limit}
        refreshCount={refreshCount}
        refreshIntervalToUse={refreshIntervalToUse}
        resources={resources}
        selectedColumnIds={selectedColumnIds}
        setPanelOptions={setPanelOptions}
        states={states}
        statuses={statuses}
      />
    </div>
  );
};

export default ResourceTable;
