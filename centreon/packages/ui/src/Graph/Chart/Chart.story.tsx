import { userAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { always, equals } from 'ramda';
import { useState } from 'react';

import type { LineChartData } from '../common/models';
import dataCurvesWithSameColor from '../mockedData/curvesWithSameColor.json';
import dataLastDay from '../mockedData/lastDay.json';
import dataLastDayWithIncompleteValues from '../mockedData/lastDayWithIncompleteValues.json';
import dataLastDayWithNullValues from '../mockedData/lastDayWithNullValues.json';
import dataPingServiceLines from '../mockedData/pingService.json';
import dataPingServiceLinesBars from '../mockedData/pingServiceLinesBars.json';
import dataPingServiceLinesBarsMixed from '../mockedData/pingServiceLinesBarsMixed.json';
import dataPingServiceLinesBarsStacked from '../mockedData/pingServiceLinesBarsStacked.json';
import WrapperChart from '.';
import { args } from './helpers/doc';
import type { LineChartProps } from './models';

const dataSets = {
  curvesWithSameColor: dataCurvesWithSameColor,
  lastDay: dataLastDay,
  lastDayWithIncompleteValues: dataLastDayWithIncompleteValues,
  lastDayWithNullValues: dataLastDayWithNullValues,
  // The connTime metric at zero on every point.
  lastDayWithZeroConnTime: {
    ...dataLastDay,
    metrics: dataLastDay.metrics.map((metric) =>
      equals(metric.metric, 'connTime')
        ? { ...metric, data: metric.data.map(always(0)) }
        : metric
    )
  },
  linesBars: dataPingServiceLinesBars,
  linesBarsMixed: dataPingServiceLinesBarsMixed,
  linesBarsStacked: dataPingServiceLinesBarsStacked,
  ping: dataPingServiceLines
};

interface Props
  extends Pick<
    LineChartProps,
    | 'additionalLines'
    | 'axis'
    | 'barStyle'
    | 'boundariesUnit'
    | 'legend'
    | 'lineStyle'
    | 'max'
    | 'min'
    | 'tooltip'
  > {
  /** Lets the user pick the units of the two axes. */
  customUnits?: boolean;
  data?: keyof typeof dataSets;
  /** Counts the secondary clicks of the legend in `secondary-clicks`. */
  secondaryClick?: boolean;
}

export const Default = ({
  customUnits = false,
  data = 'lastDay',
  secondaryClick = false,
  ...props
}: Props): JSX.Element => {
  const store = createStore();
  store.set(userAtom, {
    alias: 'admin',
    locale: 'en',
    name: 'admin',
    timezone: 'Europe/Paris'
  } as never);
  const [leftUnit, setLeftUnit] = useState('%');
  const [rightUnit, setRightUnit] = useState('ms');
  const [clicks, setClicks] = useState(0);

  return (
    <Provider store={store}>
      <WrapperChart
        {...args}
        {...props}
        axis={
          customUnits
            ? {
                axisYLeft: { onUnitChange: setLeftUnit, unit: leftUnit },
                axisYRight: { onUnitChange: setRightUnit, unit: rightUnit }
              }
            : props.axis
        }
        data={dataSets[data] as unknown as LineChartData}
        legend={
          secondaryClick
            ? ({
                ...props.legend,
                secondaryClick: () => setClicks((count) => count + 1)
              } as LineChartProps['legend'])
            : props.legend
        }
      />
      <output data-testid="secondary-clicks">{clicks}</output>
    </Provider>
  );
};
