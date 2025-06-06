import { Dispatch, ReactNode, SetStateAction, useMemo } from 'react';

import { equals, prop, slice, sortBy } from 'ramda';

import { Box, alpha, useTheme } from '@mui/material';

import { useMemoComponent } from '@centreon/ui';

import { formatMetricValue } from '../../common/timeSeries';
import { Line } from '../../common/timeSeries/models';
import { LegendModel } from '../models';
import { labelAvg, labelMax, labelMin } from '../translatedLabels';

import { useStyles } from './Legend.styles';
import LegendContent from './LegendContent';
import LegendHeader from './LegendHeader';
import { GetMetricValueProps, LegendDisplayMode } from './models';
import useLegend from './useLegend';

interface Props extends Pick<LegendModel, 'placement' | 'mode'> {
  base: number;
  height: number | null;
  limitLegend?: false | number;
  lines: Array<Line>;
  renderExtraComponent?: ReactNode;
  setLinesGraph: Dispatch<SetStateAction<Array<Line> | null>>;
  shouldDisplayLegendInCompactMode: boolean;
  toggable?: boolean;
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
  height,
  mode
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({
    limitLegendRows: Boolean(limitLegend),
    placement,
    height
  });
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

  const selectMetric = ({ event, metric_id }): void => {
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
      className={classes.legend}
      data-display-side={!equals(placement, 'bottom')}
    >
      <div
        className={classes.items}
        data-as-list={isListMode || !equals(placement, 'bottom')}
        data-mode={itemMode}
      >
        {displayedLines.map((line) => {
          const { color, display, highlight, metric_id, unit } = line;

          const markerColor = display
            ? color
            : alpha(theme.palette.text.disabled, 0.2);

          const minMaxAvg = [
            {
              label: labelMin,
              value: line.minimum_value
            },
            {
              label: labelMax,
              value: line.maximum_value
            },
            {
              label: labelAvg,
              value: line.average_value
            }
          ];

          return (
            <Box
              className={cx(
                classes.item,
                highlight ? classes.highlight : classes.normal,
                toggable && classes.toggable
              )}
              key={metric_id}
              onClick={(event): void => selectMetric({ event, metric_id })}
              onMouseEnter={(): void => highlightLine(metric_id)}
              onMouseLeave={(): void => clearHighlight()}
            >
              <LegendHeader
                color={markerColor}
                disabled={!display}
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
                  <div className={classes.minMaxAvgContainer}>
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
            </Box>
          );
        })}
      </div>
      {renderExtraComponent}
    </div>
  );
};

const Legend = (props: Props): JSX.Element => {
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
