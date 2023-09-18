import { createStore } from 'jotai';
import { extend } from 'dayjs';
import duration from 'dayjs/plugin/duration';

import { Module } from '@centreon/ui';

import Graph from './Graph';
import { Data, FormThreshold, ValueFormat } from './models';

extend(duration);

interface Props {
  globalRefreshInterval?: number;
  panelData: Data;
  panelOptions: {
    refreshInterval: 'default' | 'custom';
    refreshIntervalCustom?: number;
    singleMetricGraphType: 'text' | 'gauge' | 'bar';
    threshold: FormThreshold;
    valueFormat: ValueFormat;
  };
  store: ReturnType<typeof createStore>;
}

const SingleMetric = ({
  store,
  panelData,
  panelOptions,
  globalRefreshInterval
}: Props): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-singlemetric" store={store}>
    <Graph
      {...panelData}
      {...panelOptions}
      globalRefreshInterval={globalRefreshInterval}
    />
  </Module>
);

export default SingleMetric;
