import dayjs from 'dayjs';
import { equals, update } from 'ramda';

import dataMissingPoint from '../mockedData/dataWithMissingPoint.json';
import dataLastWeek from '../mockedData/lastWeek.json';
import dataPingService from '../mockedData/pingService.json';
import dataPingServiceMixedStacked from '../mockedData/pingServiceMixedStacked.json';
import dataPingServiceStacked from '../mockedData/pingServiceStacked.json';
import dataPingServiceLinesStackKeys from '../mockedData/pingServiceWithStackedKeys.json';
import BarChart, { type BarChartProps } from './BarChart';

// Two metrics of the 7th bar without value: one undefined, one zero.
const stackedWithUndefinedValues = {
  ...dataPingServiceStacked,
  metrics: dataPingServiceStacked.metrics.map((metric) => {
    if (equals(metric.metric_id, 3)) {
      return { ...metric, data: update(6, null, metric.data) };
    }
    if (equals(metric.metric_id, 4)) {
      return { ...metric, data: update(6, 0, metric.data) };
    }

    return metric;
  })
};

const dataSets = {
  lastWeek: dataLastWeek,
  missingPoint: dataMissingPoint,
  mixedStacked: dataPingServiceMixedStacked,
  ping: dataPingService,
  stacked: dataPingServiceStacked,
  stackedWithUndefinedValues,
  stackKeys: dataPingServiceLinesStackKeys
};

interface Props
  extends Pick<
    BarChartProps,
    'axis' | 'barStyle' | 'legend' | 'max' | 'min' | 'tooltip'
  > {
  data?: keyof typeof dataSets;
  orientation?: 'horizontal' | 'vertical';
}

export const Default = ({
  data = 'ping',
  orientation = 'horizontal',
  ...props
}: Props): JSX.Element => (
  <div style={{ height: '100%', width: '100%' }}>
    <BarChart
      {...props}
      data={dataSets[data] as never}
      end={new Date(Date.now()).toISOString()}
      height={500}
      loading={false}
      orientation={orientation}
      start={dayjs(Date.now()).subtract(24, 'hour').toISOString()}
    />
  </div>
);
