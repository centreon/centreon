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
import { useCallback, useMemo, useRef } from 'react';

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

const getBoolean = (value: unknown): boolean => Boolean(Number(value));
const defaultDsData = {
  ds_color_line: '#000000',
  ds_filled: false,
  ds_invert: false,
  ds_legend: '',
  ds_order: '0',
  ds_stack: '0',
  ds_transparency: 80
};

const useGraphData = ({ data }: Props): GraphDataResult => {
  // @ts-expect-error - suppressing pre-existing type mismatch
  const adjustedDataRef = useRef<Data>();

  const dataWithAdjustedMetricsColor = useMemo(() => {
    if (isNil(data)) {
      return data;
    }

    if (isEmpty(data.metrics)) {
      return undefined;
    }

    const metricsWithValidDsData = (data?.metrics || []).map((metric) => ({
      ...metric,
      ds_data: {
        ...defaultDsData,
        ...(metric?.ds_data || {}),
        ds_color_area:
          metric?.ds_data?.ds_color_area ??
          metric?.ds_data?.ds_color_line ??
          defaultDsData.ds_color_line
      }
    }));

    const metricsGroupedByColor = groupBy(
      // @ts-expect-error - suppressing pre-existing type mismatch
      (metric) => metric.ds_data?.ds_color_line || '#000000'
    )(metricsWithValidDsData);

    const newMetrics = Object.entries(metricsGroupedByColor).map(
      ([color, value]) => {
        const adjustedValue = value?.map((item) => ({
          // @ts-expect-error - suppressing pre-existing type mismatch
          ...item,
          ds_data: {
            // @ts-expect-error - suppressing pre-existing type mismatch
            ...item?.ds_data,
            // @ts-expect-error - suppressing pre-existing type mismatch
            ds_filled: getBoolean(item?.ds_data?.ds_filled),
            // @ts-expect-error - suppressing pre-existing type mismatch
            ds_invert: getBoolean(item?.ds_data?.ds_invert)
          }
        }));

        return adjustedValue?.map((metric, index) =>
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
    const baseAxis =
      (dataWithAdjustedMetricsColor.global.base as number) ?? 1000;
    const title = (dataWithAdjustedMetricsColor.global.title as string) ?? '';

    const newLineData = adjustGraphData(dataWithAdjustedMetricsColor).lines;

    const sortedLines = sortBy(compose(toLower, prop('name')), newLineData);

    adjustedDataRef.current = {
      baseAxis,
      lines: sortedLines,
      timeSeries,
      title
    };
  }, [dataWithAdjustedMetricsColor]);

  prepareData();

  return { adjustedData: adjustedDataRef.current };
};

export default useGraphData;
