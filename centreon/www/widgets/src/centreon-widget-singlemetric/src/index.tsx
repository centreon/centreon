import { createStore } from 'jotai';
import { extend } from 'dayjs';
import duration from 'dayjs/plugin/duration';

import { Module } from '@centreon/ui';

import Graph from './Graph';
import { Data } from './models';

extend(duration);

interface Props {
  panelData: Data;
  panelOptions;
  store: ReturnType<typeof createStore>;
}

const SingleMetric = ({
  store,
  panelData,
  panelOptions
}: Props): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-singlemetric" store={store}>
    <Graph {...panelData} {...panelOptions} />
  </Module>
);

export default SingleMetric;
