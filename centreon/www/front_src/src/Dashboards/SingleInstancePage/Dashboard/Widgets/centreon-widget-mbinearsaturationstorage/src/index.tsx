import { isEmpty } from 'ramda';
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
      dashboardId={dashboardId}
      globalRefreshInterval={globalRefreshInterval}
      id={id}
      isFromPreview={isFromPreview}
      panelData={panelData}
      panelOptions={panelOptions}
      path="/bi/widget/nearsaturationstorage"
      playlistHash={playlistHash}
      queryClient={queryClient}
      refreshCount={refreshCount}
      widgetPrefixQuery={widgetPrefixQuery}
    />
  );
};

export default Widget;
