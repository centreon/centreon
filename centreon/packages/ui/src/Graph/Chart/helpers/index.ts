import dayjs from 'dayjs';
import durationPlugin from 'dayjs/plugin/duration';
import { gt, gte, isEmpty, isNil, prop, propEq, reject, sortBy } from 'ramda';

import type { LineChartData } from '../../common/models';
import {
  getLineData,
  getTimeSeries,
  getTimeValue
} from '../../common/timeSeries';
import type { LinesData } from '../BasicComponents/Lines/models';
import { dateFormat, timeFormat } from '../common';
import type { GetDate, GraphInterval } from '../models';

dayjs.extend(durationPlugin);

export const adjustGraphData = (graphData: LineChartData): LinesData => {
  const lines = getLineData(graphData);
  const sortedLines = sortBy(prop('name'), lines);
  const displayedLines = reject(propEq(false, 'display'), sortedLines);

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

export const truncate = ({
  content,
  maxLength = 180
}: { content?: string; maxLength?: number }): string => {
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

export const getDate = ({ positionX, xScale, timeSeries }: GetDate): Date => {
  const timeValue = getTimeValue({
    timeSeries,
    x: positionX,
    xScale
  });

  return new Date(timeValue?.timeTick || 0);
};
