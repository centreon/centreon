import { ReactElement } from 'react';
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
  queryClient,
  isInViewport
}: WidgetProps): ReactElement => {
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
      path="/bi/widget/baavailability"
      playlistHash={playlistHash}
      queryClient={queryClient}
      refreshCount={refreshCount}
      widgetPrefixQuery={widgetPrefixQuery}
      isInViewport={isInViewport}
    />
  );
};

export default Widget;
