import { Group } from '@visx/group';
import { BarGroup } from '@visx/shape/lib/types';
import { ScaleLinear } from 'd3-scale';
import { equals, omit } from 'ramda';
import { memo } from 'react';
import { Line, TimeValue } from '../common/timeSeries/models';
import BarStack from './BarStack';
import { BarStyle } from './models';

interface Props {
  neutralValue: number;
  isTooltipHidden: boolean;
  barStyle: BarStyle;
  yScalesPerUnit: Record<string, ScaleLinear<number, number>>;
  stackedLinesTimeSeriesPerStackKeyAndUnit: Record<
    string,
    { lines: Array<Line>; timeSeries: Array<TimeValue> }
  >;
  notStackedLines: Array<Line>;
  notStackedTimeSeries: Array<TimeValue>;
  isHorizontal: boolean;
  barGroup: BarGroup<'id'>;
  barIndex: number;
}

const MemoizedGroup = ({
  barGroup,
  stackedLinesTimeSeriesPerStackKeyAndUnit,
  notStackedLines,
  notStackedTimeSeries,
  isHorizontal,
  barStyle,
  isTooltipHidden,
  neutralValue,
  yScalesPerUnit,
  barIndex
}: Props): JSX.Element | null => {
  const hasEmptyValues = barGroup.bars.every(({ key, value }) => {
    if (key.startsWith('stacked-')) {
      const timeValueBar =
        stackedLinesTimeSeriesPerStackKeyAndUnit[key].timeSeries[barIndex];

      return Object.values(omit(['timeTick'], timeValueBar)).every(
        (value) => !value
      );
    }

    return !value;
  });

  if (hasEmptyValues) {
    return null;
  }

  return (
    <Group left={barGroup.x0} top={barGroup.y0}>
      {barGroup.bars.map((bar) => {
        const isStackedBar = bar.key.startsWith('stacked-');
        const linesBar = isStackedBar
          ? stackedLinesTimeSeriesPerStackKeyAndUnit[bar.key].lines
          : (notStackedLines.find(({ metric_id }) =>
              equals(metric_id, Number(bar.key))
            ) as Line);
        const timeSeriesBar = isStackedBar
          ? stackedLinesTimeSeriesPerStackKeyAndUnit[bar.key].timeSeries
          : notStackedTimeSeries.map((timeSerie) => ({
              timeTick: timeSerie.timeTick,
              [bar.key]: timeSerie[Number(bar.key)]
            }));

        const unit = isStackedBar
          ? bar.key.split('-')[1]
          : (linesBar as Line).unit;
        const yScale =
          unit === '' && yScalesPerUnit[unit] === undefined
            ? yScalesPerUnit[undefined]
            : yScalesPerUnit[unit];

        return isStackedBar ? (
          <BarStack
            isStacked
            key={`bar-${barGroup.index}-${bar.width}-${bar.y}-${bar.height}-${bar.x}`}
            barIndex={barGroup.index}
            barPadding={isHorizontal ? bar.x : bar.y}
            barStyle={barStyle}
            barWidth={isHorizontal ? bar.width : bar.height}
            isHorizontal={isHorizontal}
            isTooltipHidden={isTooltipHidden}
            lines={linesBar as Array<Line>}
            timeSeries={timeSeriesBar}
            yScale={yScale}
            neutralValue={neutralValue}
          />
        ) : (
          <BarStack
            key={`bar-${barGroup.index}-${bar.width}-${bar.y}-${bar.height}-${bar.x}`}
            barIndex={barGroup.index}
            barPadding={isHorizontal ? bar.x : bar.y}
            barStyle={barStyle}
            barWidth={isHorizontal ? bar.width : bar.height}
            isHorizontal={isHorizontal}
            isTooltipHidden={isTooltipHidden}
            lines={[linesBar as Line]}
            timeSeries={timeSeriesBar}
            yScale={yScale}
            neutralValue={neutralValue}
          />
        );
      })}
    </Group>
  );
};

export default memo(
  MemoizedGroup,
  (prevProps, nextProps) =>
    equals(prevProps.barGroup, nextProps.barGroup) &&
    equals(
      prevProps.stackedLinesTimeSeriesPerStackKeyAndUnit,
      nextProps.stackedLinesTimeSeriesPerStackKeyAndUnit
    ) &&
    equals(prevProps.notStackedLines, nextProps.notStackedLines) &&
    equals(prevProps.notStackedTimeSeries, nextProps.notStackedTimeSeries) &&
    equals(prevProps.isHorizontal, nextProps.isHorizontal) &&
    equals(prevProps.barStyle, nextProps.barStyle) &&
    equals(prevProps.isTooltipHidden, nextProps.isTooltipHidden) &&
    equals(prevProps.neutralValue, nextProps.neutralValue) &&
    equals(prevProps.barIndex, nextProps.barIndex)
);
