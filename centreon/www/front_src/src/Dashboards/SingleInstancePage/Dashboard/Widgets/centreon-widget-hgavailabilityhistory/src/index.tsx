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
      dashboardId={dashboardId}
      globalRefreshInterval={globalRefreshInterval}
      id={id}
      isFromPreview={isFromPreview}
      panelData={panelData}
      panelOptions={panelOptions}
      path="/bi/widget/hgavailabilityhistory"
      playlistHash={playlistHash}
      queryClient={queryClient}
      refreshCount={refreshCount}
      widgetPrefixQuery={widgetPrefixQuery}
    />
  );
};

export default Widget;
