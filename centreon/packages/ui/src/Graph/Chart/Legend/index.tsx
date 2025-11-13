import {
  Dispatch,
  KeyboardEvent,
  MouseEvent,
  ReactElement,
  ReactNode,
  SetStateAction,
  useMemo
} from 'react';

import { equals, prop, slice, sortBy } from 'ramda';

import { alpha, useTheme } from '@mui/material';

import { useMemoComponent } from '@centreon/ui';

import { formatMetricValue } from '../../common/timeSeries';
import { Line } from '../../common/timeSeries/models';
import { LegendModel } from '../models';
import { labelAvg, labelMax, labelMin } from '../translatedLabels';
import LegendContent from './LegendContent';
import LegendHeader from './LegendHeader';
import { GetMetricValueProps, LegendDisplayMode } from './models';
import useLegend from './useLegend';

interface Props
  extends Pick<LegendModel, 'placement' | 'mode' | 'showCalculations'> {
  base: number;
  height: number | null;
  limitLegend?: false | number;
  lines: Array<Line>;
  renderExtraComponent?: ReactNode;
  setLinesGraph: Dispatch<SetStateAction<Array<Line> | null>>;
  shouldDisplayLegendInCompactMode: boolean;
  toggable?: boolean;
  secondaryClick?: (props: {
    element: EventTarget | null;
    metricId: number | string;
    position: [number, number];
  }) => void;
  graphHeight: number;
}

const MainLegend = ({
  lines,
  base,
  toggable = true,
  limitLegend = false,
  renderExtraComponent,
  setLinesGraph,
  shouldDisplayLegendInCompactMode,
  placement,
  mode,
  showCalculations = {
    min: true,
    max: true,
    avg: true
  },
  secondaryClick,
  graphHeight
}: Props): ReactElement => {
  const theme = useTheme();

  const { selectMetricLine, clearHighlight, highlightLine, toggleMetricLine } =
    useLegend({ lines, setLinesGraph });

  const sortedData = sortBy(prop('metric_id'), lines);

  const isListMode = useMemo(() => equals(mode, 'list'), [mode]);

  const displayedLines = limitLegend
    ? slice(0, limitLegend, sortedData)
    : sortedData;

  const getMetricValue = ({ value, unit }: GetMetricValueProps): string =>
    formatMetricValue({
      base,
      unit,
      value
    }) || 'N/A';

  const contextMenuClick =
    (metricId: number) =>
      (event: MouseEvent): void => {
        if (!secondaryClick) {
          return;
        }
        event.preventDefault();
        secondaryClick({
          element: event.target,
          metricId,
          position: [event.pageX, event.pageY]
        });
      };

  const selectMetric = ({
    event,
    metric_id
  }: {
    event: MouseEvent<HTMLLIElement> | KeyboardEvent<HTMLLIElement>;
    metric_id: number;
  }): void => {
    if (!toggable) {
      return;
    }

    if (event.ctrlKey || event.metaKey) {
      toggleMetricLine(metric_id);

      return;
    }

    selectMetricLine(metric_id);
  };

  const itemMode =
    !isListMode && shouldDisplayLegendInCompactMode
      ? LegendDisplayMode.Compact
      : LegendDisplayMode.Normal;

  return (
    <div
      className={`overflow-x-hidden overflow-y-auto ${!equals(placement, 'bottom') ? 'h-full mt-[15px]' : 'ml-[50px] mr-[40px]'} legend`}
      data-display-side={!equals(placement, 'bottom')}
    >
      <ul
        className={`list-none flex gap-3 w-full ${!isListMode && equals(placement, 'bottom') && 'flex-wrap'} ${isListMode || !equals(placement, 'bottom') ? 'flex-col h-full w-fit' : ''} ${equals(placement, 'bottom') ? 'max-h-[68px]' : 'max-h-0'}`}
        style={{
          height: equals(placement, 'bottom') ? 'auto' : `${graphHeight}px`
        }}
        data-as-list={isListMode || !equals(placement, 'bottom')}
        data-mode={itemMode}
      >
        {displayedLines.map((line) => {
          const { color, display, metric_id, unit } = line;

          const markerColor = display
            ? color
            : alpha(theme.palette.text.disabled, 0.2);

          const minMaxAvg = [
            showCalculations.min && {
              label: labelMin,
              value: line.minimum_value
            },
            showCalculations.max && {
              label: labelMax,
              value: line.maximum_value
            },
            showCalculations.avg && {
              label: labelAvg,
              value: line.average_value
            }
          ].filter(Boolean);

          return (
            <li
              className={`${!display ? 'text-text-disabled' : 'text-text-primary'} flex gap-1 ${toggable && 'cursor-pointer'}`}
              key={metric_id}
              onClick={(event): void => selectMetric({ event, metric_id })}
              onKeyUp={(event) =>
                event.key === 'Enter' && selectMetric({ event, metric_id })
              }
              onMouseEnter={(): void => highlightLine(metric_id)}
              onMouseLeave={(): void => clearHighlight()}
              onContextMenu={contextMenuClick(metric_id)}
            >
              <div
                className="h-full rounded-sm w-1 min-h-4"
                style={{ backgroundColor: markerColor }}
                data-icon
              />
              <div>
                <LegendHeader
                  isDisplayedOnSide={!equals(placement, 'bottom')}
                  isListMode={isListMode}
                  line={line}
                  minMaxAvg={
                    shouldDisplayLegendInCompactMode ? minMaxAvg : undefined
                  }
                  unit={unit}
                />
                {!shouldDisplayLegendInCompactMode && !isListMode && (
                  <div>
                    <div className="flex flex-wrap gap-1 whitespace-nowrap">
                      {minMaxAvg.map(({ label, value }) => (
                        <LegendContent
                          data={getMetricValue({ unit: line.unit, value })}
                          key={label}
                          label={label}
                        />
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </li>
          );
        })}
      </ul>
      {renderExtraComponent}
    </div>
  );
};

const Legend = (props: Props): ReactElement => {
  const {
    toggable,
    limitLegend,
    lines,
    base,
    shouldDisplayLegendInCompactMode,
    placement,
    height,
    mode
  } = props;

  return useMemoComponent({
    Component: <MainLegend {...props} />,
    memoProps: [
      lines,
      base,
      toggable,
      limitLegend,
      shouldDisplayLegendInCompactMode,
      placement,
      height,
      mode
    ]
  });
};

export default Legend;
