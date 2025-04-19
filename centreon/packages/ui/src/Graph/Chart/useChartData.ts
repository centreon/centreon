import { useCallback, useMemo, useRef } from 'react';

import {
  compose,
  flatten,
  groupBy,
  isEmpty,
  isNil,
  lensPath,
  pipe,
  prop,
  set,
  sortBy,
  toLower
} from 'ramda';

import type { LineChartData } from '../common/models';
import { emphasizeCurveColor } from '../common/utils';

import { adjustGraphData } from './helpers';
import type { Data } from './models';
import { clampValue } from '../common/timeSeries';

interface GraphDataResult {
  adjustedData?: Data;
}

interface Props {
  data?: LineChartData;
  end?: string;
  start?: string;
  min?: number;
  max?: number;
}

const clampTimeSeries = ({ timeSeries, min, max }) =>
  timeSeries.map((timeSerie) => {
    return Object.entries(timeSerie).reduce((acc, [key, value]) => {
      if (key === 'timeTick') {
        return { ...acc, [key]: value };
      }

      return {
        ...acc,
        [key]: clampValue({ value: value as number | null, min, max })
      };
    }, {});
  });

const useGraphData = ({
  data,
  end,
  start,
  min,
  max
}: Props): GraphDataResult => {
  const adjustedDataRef = useRef<Data>();

  const dataWithAdjustedMetricsColor = useMemo(() => {
    if (isNil(data)) {
      return data;
    }

    if (isEmpty(data.metrics) || isEmpty(data.times)) {
      return undefined;
    }

    const metricsGroupedByColor = groupBy(
      (metric) => metric.ds_data.ds_color_line
    )(data?.metrics || []);

    const newMetrics = Object.entries(metricsGroupedByColor).map(
      ([color, value]) => {
        return value?.map((metric, index) =>
          set(
            lensPath(['ds_data', 'ds_color_line']),
            emphasizeCurveColor({ color, index }),
            metric
          )
        );
      }
    );

    const sortedMetrics = pipe(flatten, sortBy(prop('metric')))(newMetrics);

    return {
      ...data,
      metrics: sortedMetrics
    };
  }, [data]);

  const prepareData = useCallback((): void => {
    if (isNil(dataWithAdjustedMetricsColor)) {
      return;
    }

    const { timeSeries } = adjustGraphData(dataWithAdjustedMetricsColor);
    const baseAxis = dataWithAdjustedMetricsColor.global.base;
    const { title } = dataWithAdjustedMetricsColor.global;

    const newLineData = adjustGraphData(dataWithAdjustedMetricsColor).lines;
    const sortedLines = sortBy(compose(toLower, prop('name')), newLineData);

    adjustedDataRef.current = {
      baseAxis,
      lines: sortedLines,
      timeSeries: clampTimeSeries({ timeSeries, min, max }),
      title
    };
  }, [dataWithAdjustedMetricsColor, end, start]);

  prepareData();

  return { adjustedData: adjustedDataRef.current };
};

export default useGraphData;
