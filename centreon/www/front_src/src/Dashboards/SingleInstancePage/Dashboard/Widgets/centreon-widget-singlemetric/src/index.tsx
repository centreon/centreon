import { CommonWidgetProps, Data } from '../../models';

import Graph from './Graph';
import { FormThreshold, ValueFormat } from './models';

interface Props extends CommonWidgetProps<object> {
  panelData: Data;
  panelOptions: {
    displayType: 'text' | 'gauge' | 'bar';
    refreshInterval: 'default' | 'custom';
    refreshIntervalCustom?: number;
    threshold: FormThreshold;
    valueFormat: ValueFormat;
  };
}

const SingleMetric = ({
  panelData,
  panelOptions,
  globalRefreshInterval,
  refreshCount,
  isFromPreview,
  playlistHash,
  dashboardId,
  id,
  widgetPrefixQuery
}: Props): JSX.Element => (
  <Graph
    {...panelData}
    {...panelOptions}
    dashboardId={dashboardId}
    globalRefreshInterval={globalRefreshInterval}
    id={id}
    isFromPreview={isFromPreview}
    playlistHash={playlistHash}
    refreshCount={refreshCount}
    widgetPrefixQuery={widgetPrefixQuery}
  />
);

export default SingleMetric;
