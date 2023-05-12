import { ReactNode } from 'react';

import { slice } from 'ramda';

import { Box, alpha, useTheme } from '@mui/material';

import { maxLinesDisplayedLegend } from '../common';
import { formatMetricValue } from '../timeSeries';
import { Line, TimeValue } from '../timeSeries/models';
import { labelAvg, labelMax, labelMin } from '../translatedLabels';

import InteractiveValue from './InteractiveValue';
import { useStyles } from './Legend.styles';
import LegendContent from './LegendContent';
import LegendHeader from './LegendHeader';
import LegendMarker from './Marker';
import { GetMetricValueProps } from './models';
import useInteractiveValues from './useInteractiveValues';
import useLegend from './useLegend';

interface Props {
  base: number;
  limitLegendRows?: boolean;
  lines: Array<Line>;
  renderExtraComponent?: ReactNode;
  timeSeries: Array<TimeValue>;
  toggable?: boolean;
}

const Legend = ({
  lines,
  timeSeries,
  base,
  toggable = true,
  limitLegendRows = true,
  renderExtraComponent
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ limitLegendRows });
  const theme = useTheme();

  const { selectMetricLine, clearHighlight, highlightLine, toggleMetricLine } =
    useLegend({ lines });

  const { getFormattedValue } = useInteractiveValues({
    base,
    lines,
    timeSeries
  });

  const displayedLines = limitLegendRows
    ? slice(0, maxLinesDisplayedLegend, lines)
    : lines;

  const getMetricValue = ({ value, unit }: GetMetricValueProps): string =>
    formatMetricValue({
      base,
      unit,
      value
    }) || 'N/A';

  const selectMetric = ({ event, metric }): void => {
    if (!toggable) {
      return;
    }

    if (event.ctrlKey || event.metaKey) {
      toggleMetricLine(metric);

      return;
    }

    selectMetricLine(metric);
  };

  return (
    <div className={classes.legend}>
      <div className={classes.items}>
        {displayedLines.map((line) => {
          const { color, name, display, metric, highlight } = line;

          const markerColor = display
            ? color
            : alpha(theme.palette.text.disabled, 0.2);

          const interactiveValue = getFormattedValue(line);

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
              key={name}
              onClick={(event): void => selectMetric({ event, metric })}
              onMouseEnter={(): void => highlightLine(metric)}
              onMouseLeave={(): void => clearHighlight()}
            >
              <LegendMarker color={markerColor} disabled={!display} />
              <div className={classes.legendData}>
                <LegendHeader line={line} />
                <InteractiveValue value={interactiveValue} />
                {!interactiveValue && (
                  <div className={classes.minMaxAvgContainer}>
                    {minMaxAvg.map(({ label, value }) => (
                      <LegendContent
                        data={getMetricValue({ unit: line.unit, value })}
                        key={label}
                        label={label}
                      />
                    ))}
                  </div>
                )}
              </div>
            </Box>
          );
        })}
      </div>
      {renderExtraComponent}
    </div>
  );
};

export default Legend;
