import { useRefreshInterval } from '@centreon/ui';

import { Listing } from './Listing';
import { ResourcesTableProps } from './models';

const StatusGrid = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount
}: ResourcesTableProps): JSX.Element => {
  const {
    displayType,
    refreshInterval,
    refreshIntervalCustom,
    states,
    statuses
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
        refreshCount={refreshCount}
        refreshIntervalToUse={refreshIntervalToUse}
        resources={resources}
        states={states}
        statuses={statuses}
      />
    </div>
  );
};

export default StatusGrid;
