import { createStore } from 'jotai';
import { extend } from 'dayjs';
import duration from 'dayjs/plugin/duration';

import { Module } from '@centreon/ui';

import { Data, GlobalRefreshInterval } from '../../models';

import Graph from './Graph';
import { FormThreshold, ValueFormat } from './models';

extend(duration);

interface Props {
  globalRefreshInterval: GlobalRefreshInterval;
  isFromPreview?: boolean;
  panelData: Data;
  panelOptions: {
    displayType: 'text' | 'gauge' | 'bar';
    refreshInterval: 'default' | 'custom';
    refreshIntervalCustom?: number;
    threshold: FormThreshold;
    valueFormat: ValueFormat;
  };
  refreshCount: number;
  store: ReturnType<typeof createStore>;
}

const SingleMetric = ({
  store,
  panelData,
  panelOptions,
  globalRefreshInterval,
  refreshCount,
  isFromPreview
}: Props): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-singlemetric" store={store}>
    <Graph
      {...panelData}
      {...panelOptions}
      globalRefreshInterval={globalRefreshInterval}
      isFromPreview={isFromPreview}
      refreshCount={refreshCount}
    />
  </Module>
);

export default SingleMetric;
