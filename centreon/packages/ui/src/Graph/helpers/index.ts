import {
  gte,
  isNil,
  prop,
  propEq,
  reject,
  sortBy,
  gt,
  isEmpty,
  equals
} from 'ramda';
import dayjs from 'dayjs';

import { getLineData, getTimeSeries } from '../timeSeries';
import { LinesData } from '../BasicComponents/Lines/models';
import { GraphData, GraphInterval } from '../models';
import { dateFormat, timeFormat } from '../common';
import { Line } from '../timeSeries/models';

export const adjustGraphData = (graphData: GraphData): LinesData => {
  const lines = getLineData(graphData);
  const sortedLines = sortBy(prop('name'), lines);
  const displayedLines = reject(propEq('display', false), sortedLines);

  const timeSeries = getTimeSeries(graphData);

  return { lines: displayedLines, timeSeries };
};

export const getXAxisTickFormat = (graphInterval: GraphInterval): string => {
  if (
    isNil(graphInterval) ||
    isNil(graphInterval?.start) ||
    isNil(graphInterval?.end)
  ) {
    return timeFormat;
  }
  const { end, start } = graphInterval;
  const numberDays = dayjs.duration(dayjs(end).diff(dayjs(start))).asDays();

  return gte(numberDays, 2) ? dateFormat : timeFormat;
};

export const truncate = (content?: string): string => {
  const maxLength = 180;

  if (isNil(content)) {
    return '';
  }

  if (gt(content.length, maxLength)) {
    return `${content.substring(0, maxLength)}...`;
  }

  return content;
};

export const displayArea = (data: unknown): boolean =>
  !isEmpty(data) && !isNil(data);

export const lowerLineName = 'Lower Threshold';
export const upperLineName = 'Upper Threshold';

export const findLineOfOriginMetricThreshold = (
  lines: Array<Line>
): Array<Line> => {
  const metrics = lines.map((line) => {
    const { metric } = line;

    return metric.includes('_upper_thresholds')
      ? metric.replace('_upper_thresholds', '')
      : null;
  });

  const originMetric = metrics.find((element) => element);

  return reject((line: Line) => !equals(line.name, originMetric), lines);
};
