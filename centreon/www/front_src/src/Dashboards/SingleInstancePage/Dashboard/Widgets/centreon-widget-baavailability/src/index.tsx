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
      isInViewport={isInViewport}
      panelData={panelData}
      panelOptions={panelOptions}
      refreshCount={refreshCount}
      isFromPreview={isFromPreview}
      path="/bi/widget/baavailability"
      playlistHash={playlistHash}
      queryClient={queryClient}
      widgetPrefixQuery={widgetPrefixQuery}
    />
  );
};

export default Widget;
