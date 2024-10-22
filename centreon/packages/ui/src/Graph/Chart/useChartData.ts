import { useCallback, useMemo, useRef } from 'react';

import {
  compose,
  flatten,
  groupBy,
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

interface GraphDataResult {
  adjustedData?: Data;
}

interface Props {
  data?: LineChartData;
  end?: string;
  start?: string;
}

const useGraphData = ({ data, end, start }: Props): GraphDataResult => {
  const adjustedDataRef = useRef<Data>();

  const dataWithAdjustedMetricsColor = useMemo(() => {
    if (isNil(data)) {
      return data;
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
      timeSeries,
      title
    };
  }, [dataWithAdjustedMetricsColor, end, start]);

  prepareData();

  return { adjustedData: adjustedDataRef.current };
};

export default useGraphData;
