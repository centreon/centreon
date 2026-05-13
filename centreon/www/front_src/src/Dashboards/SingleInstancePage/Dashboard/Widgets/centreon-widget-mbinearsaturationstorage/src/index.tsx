import { isEmpty } from 'ramda';
import FederatedComponent from '../../../../../../components/FederatedComponents';
import NoResources from '../../NoResources';
import { areResourcesFullfilled } from '../../utils';

import { WidgetProps } from './models';
import { ReactElement } from 'react';

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
}: WidgetProps): ReactElement => {
  if (
    !areResourcesFullfilled(panelData.resources) ||
    isEmpty(panelData.metrics)
  ) {
    return <NoResources />;
  }
  return (
    <FederatedComponent
      path="/bi/widget/nearsaturationstorage"
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
