import type { CommonWidgetProps, Data } from '../../models';

import { Module } from '@centreon/ui';
import FederatedComponent from '../../../../front_src/src/components/FederatedComponents';
import NoResources from '../../NoResources';
import { areResourcesFullfilled } from '../../utils';
import { PanelOptions } from './models';

interface Props extends CommonWidgetProps<PanelOptions> {
  panelData: Data;
  panelOptions: PanelOptions;
}
const Widget = ({
  panelData,
  panelOptions,
  globalRefreshInterval,
  refreshCount,
  id,
  playlistHash,
  dashboardId,
  widgetPrefixQuery,
  queryClient,
  store
}: Props): JSX.Element => {
  if (!areResourcesFullfilled(panelData.resources)) {
    return <NoResources />;
  }

  return (
    <Module seedName="centreon-widget-batree" store={store}>
      <FederatedComponent
        dashboardId={dashboardId}
        globalRefreshInterval={globalRefreshInterval}
        id={id}
        panelData={panelData}
        panelOptions={panelOptions}
        playlistHash={playlistHash}
        refreshCount={refreshCount}
        widgetPrefixQuery={widgetPrefixQuery}
        path="/bam/widget"
        queryClient={queryClient}
        store={store}
      />
    </Module>
  );
};

export default Widget;

