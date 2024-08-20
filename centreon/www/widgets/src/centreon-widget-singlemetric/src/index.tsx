import { extend } from 'dayjs';
import duration from 'dayjs/plugin/duration';

import { Module } from '@centreon/ui';

import { Data, CommonWidgetProps } from '../../models';

import Graph from './Graph';
import { FormThreshold, ValueFormat } from './models';

extend(duration);

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
  store,
  panelData,
  panelOptions,
  globalRefreshInterval,
  refreshCount,
  isFromPreview,
  playlistHash,
  dashboardId,
  id,
  queryClient,
  widgetPrefixQuery
}: Props): JSX.Element => (
  <Module
    maxSnackbars={1}
    queryClient={queryClient}
    seedName="widget-singlemetric"
    store={store}
  >
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
  </Module>
);

export default SingleMetric;
