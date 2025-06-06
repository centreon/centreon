import FederatedComponent from '../../../../../../components/FederatedComponents';
import NoResources from '../../NoResources';
import { areResourcesFullfilled } from '../../utils';

import { WidgetProps } from './models';

const Widget = ({
  panelData,
  id,
  dashboardId,
  globalRefreshInterval,
  panelOptions,
  widgetPrefixQuery,
  refreshCount,
  playlistHash,
  isFromPreview,
  queryClient
}: WidgetProps): JSX.Element => {
  if (!areResourcesFullfilled(panelData.resources)) {
    return <NoResources />;
  }

  return (
    <FederatedComponent
      path="/bi/widget/baavailability"
      panelData={panelData}
      panelOptions={panelOptions}
      globalRefreshInterval={globalRefreshInterval}
      refreshCount={refreshCount}
      isFromPreview={isFromPreview}
      id={id}
      playlistHash={playlistHash}
      dashboardId={dashboardId}
      queryClient={queryClient}
      widgetPrefixQuery={widgetPrefixQuery}
    />
  );
};

export default Widget;
