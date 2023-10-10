import { Dispatch, ReactNode, SetStateAction } from 'react';

import { prop, slice, sortBy } from 'ramda';
import { useAtomValue } from 'jotai';

import { Box, alpha, useTheme } from '@mui/material';

import { useMemoComponent } from '@centreon/ui';

import { maxLinesDisplayedLegend } from '../common';
import { formatMetricValue } from '../../common/timeSeries';
import { Line, TimeValue } from '../../common/timeSeries/models';
import { labelAvg, labelMax, labelMin } from '../translatedLabels';
import { timeValueAtom } from '../InteractiveComponents/interactionWithGraphAtoms';

import InteractiveValue from './InteractiveValue';
import { useStyles } from './Legend.styles';
import LegendHeader from './LegendHeader';
import LegendMarker from './Marker';
import { GetMetricValueProps } from './models';
import useInteractiveValues from './useInteractiveValues';
import useLegend from './useLegend';
import LegendContent from './LegendContent';

interface Props {
  base: number;
  displayAnchor?: boolean;
  limitLegendRows?: boolean;
  lines: Array<Line>;
  renderExtraComponent?: ReactNode;
  setLinesGraph: Dispatch<SetStateAction<Array<Line> | null>>;
  timeSeries: Array<TimeValue>;
  toggable?: boolean;
  xScale;
}

const MainLegend = ({
  lines,
  timeSeries,
  base,
  toggable = true,
  limitLegendRows = true,
  renderExtraComponent,
  displayAnchor = true,
  setLinesGraph,
  xScale
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ limitLegendRows });
  const theme = useTheme();

  const { selectMetricLine, clearHighlight, highlightLine, toggleMetricLine } =
    useLegend({ lines, setLinesGraph });

  const { getFormattedValue } = useInteractiveValues({
    base,
    lines,
    timeSeries,
    xScale
  });

  const sortedData = sortBy(prop('metric_id'), lines);

  const displayedLines = limitLegendRows
    ? slice(0, maxLinesDisplayedLegend, sortedData)
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

  return (
    <div className={classes.legend}>
      <div className={classes.items}>
        {displayedLines.map((line) => {
          const { color, display, highlight, metric_id } = line;

          const markerColor = display
            ? color
            : alpha(theme.palette.text.disabled, 0.2);

          const interactiveValue = displayAnchor
            ? getFormattedValue(line)
            : null;

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
              <LegendMarker color={markerColor} disabled={!display} />
              <div className={classes.legendData}>
                <LegendHeader line={line} />
                {displayAnchor && <InteractiveValue value={interactiveValue} />}
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

const Legend = (props: Props): JSX.Element => {
  const { toggable, limitLegendRows, timeSeries, lines, base, displayAnchor } =
    props;
  const timeValue = useAtomValue(timeValueAtom);

  return useMemoComponent({
    Component: <MainLegend {...props} />,
    memoProps: [
      timeValue,
      timeSeries,
      lines,
      base,
      toggable,
      limitLegendRows,
      displayAnchor
    ]
  });
};

export default Legend;
