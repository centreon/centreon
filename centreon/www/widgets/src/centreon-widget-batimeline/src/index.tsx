import { Module } from '@centreon/ui';

import FederatedComponent from '../../../../front_src/src/components/FederatedComponents';

import type { CommonWidgetProps, Data } from '../../models';

import type { PanelOptions } from './models';

import { equals, last, pluck } from 'ramda';
import NoResources from '../../NoResources';
import { areResourcesFullfilled } from '../../utils';
import { labelSelectBAToDisplayPreview } from './translatedLabels';

interface Props extends CommonWidgetProps<PanelOptions> {
  panelData: Data;
  panelOptions: PanelOptions;
}

const Widget = ({
  store,
  panelData,
  panelOptions,
  globalRefreshInterval,
  refreshCount,
  id,
  playlistHash,
  dashboardId,
  widgetPrefixQuery,
  queryClient
}: Props): JSX.Element => {
  const lastSelectedResourceType = last(
    pluck('resourceType', panelData.resources)
  );

  const isBASelected =
    equals('business-activity', lastSelectedResourceType) &&
    areResourcesFullfilled(panelData.resources);

  if (!isBASelected) {
    return <NoResources label={labelSelectBAToDisplayPreview} />;
  }

  return (
    <Module
      maxSnackbars={1}
      seedName="centreon-widget-batimeline"
      store={store}
    >
      <FederatedComponent
        dashboardId={dashboardId}
        globalRefreshInterval={globalRefreshInterval}
        id={id}
        panelData={panelData}
        panelOptions={panelOptions}
        playlistHash={playlistHash}
        refreshCount={refreshCount}
        widgetPrefixQuery={widgetPrefixQuery}
        path="/bam/widgets/batimeline"
        store={store}
        queryClient={queryClient}
      />
    </Module>
  );
};

export default Widget;
