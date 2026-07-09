import { ReactElement } from 'react';

import { CommonWidgetProps, Data } from '../../models';
import LineChart from './LineChart';
import { PanelOptions } from './models';

interface Props extends CommonWidgetProps<PanelOptions> {
  panelData: Data;
  panelOptions: PanelOptions;
}

const Input = ({
  panelData,
  panelOptions,
  globalRefreshInterval,
  refreshCount,
  id,
  playlistHash,
  dashboardId,
  widgetPrefixQuery,
  isFromPreview,
  isInViewport
}: Props): ReactElement => {
  return (
    <LineChart
      dashboardId={dashboardId}
      globalRefreshInterval={globalRefreshInterval}
      id={id}
      isFromPreview={isFromPreview}
      isInViewport={isInViewport}
      panelData={panelData}
      panelOptions={panelOptions}
      playlistHash={playlistHash}
      refreshCount={refreshCount}
      widgetPrefixQuery={widgetPrefixQuery}
    />
  );
};

export default Input;
