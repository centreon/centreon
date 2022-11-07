<<<<<<< HEAD
/* eslint-disable hooks/sort */
import { MouseEvent } from 'react';

import clsx from 'clsx';
import { equals, find, gt, includes, isNil, length, slice, split } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai/utils';

import {
  Typography,
=======
import * as React from 'react';

import clsx from 'clsx';
import {
  equals,
  find,
  gt,
  includes,
  length,
  propOr,
  slice,
  split,
} from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Typography,
  makeStyles,
>>>>>>> centreon/dev-21.10.x
  useTheme,
  alpha,
  Theme,
  Tooltip,
  Box,
  Button,
<<<<<<< HEAD
} from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import BarChartIcon from '@mui/icons-material/BarChart';
import { CreateCSSProperties } from '@mui/styles';

import { Line, TimeValue } from '../models';
import memoizeComponent from '../../../memoizedComponent';
=======
} from '@material-ui/core';
import BarChartIcon from '@material-ui/icons/BarChart';
import { CreateCSSProperties } from '@material-ui/styles';

import { ResourceContext, useResourceContext } from '../../../Context';
import { Line } from '../models';
import memoizeComponent from '../../../memoizedComponent';
import { useMetricsValueContext } from '../Graph/useMetricsValue';
>>>>>>> centreon/dev-21.10.x
import formatMetricValue from '../formatMetricValue/index';
import {
  labelAvg,
  labelDisplayCompleteGraph,
  labelMax,
  labelMin,
} from '../../../translatedLabels';
<<<<<<< HEAD
import { timeValueAtom } from '../Graph/mouseTimeValueAtoms';
import { getLineForMetric, getMetrics } from '../timeSeries';
import { panelWidthStorageAtom } from '../../../Details/detailsAtoms';
=======
>>>>>>> centreon/dev-21.10.x

import LegendMarker from './Marker';

interface MakeStylesProps {
  limitLegendRows: boolean;
  panelWidth: number;
}

<<<<<<< HEAD
interface FormattedMetricData {
  color: string;
  formattedValue: string | null;
  name: string;
  unit: string;
}

=======
>>>>>>> centreon/dev-21.10.x
const maxLinesDisplayed = 11;

const useStyles = makeStyles<Theme, MakeStylesProps, string>((theme) => ({
  caption: ({ panelWidth }): CreateCSSProperties<MakeStylesProps> => ({
    lineHeight: 1.2,
    marginRight: theme.spacing(0.5),
    maxWidth: 0.85 * panelWidth,
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
  }),
  highlight: {
    color: theme.typography.body1.color,
  },
  item: {
    display: 'grid',
    gridTemplateColumns: 'min-content minmax(50px, 1fr)',
    marginBottom: theme.spacing(1),
  },
  items: ({ limitLegendRows }): CreateCSSProperties<MakeStylesProps> => ({
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))',
    justifyContent: 'center',
    marginLeft: theme.spacing(0.5),
    maxHeight: limitLegendRows ? theme.spacing(19) : 'unset',
    overflowY: 'auto',
    width: '100%',
  }),
  legend: {
    maxHeight: theme.spacing(24),
    overflowX: 'hidden',
    overflowY: 'auto',
    width: '100%',
  },
  legendData: {
    display: 'flex',
    flexDirection: 'column',
  },
  legendName: {
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'start',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
  },
  legendUnit: {
    justifyContent: 'end',
    marginLeft: 'auto',
    marginRight: theme.spacing(0.5),
    overflow: 'hidden',
    textOverflow: 'ellipsis',
  },
  legendValue: {
    fontWeight: theme.typography.body1.fontWeight,
  },
  minMaxAvgContainer: {
    columnGap: theme.spacing(0.5),
    display: 'grid',
<<<<<<< HEAD
    gridAutoRows: theme.spacing(2),
=======
    gridAutoRows: `${theme.spacing(2)}px`,
>>>>>>> centreon/dev-21.10.x
    gridTemplateColumns: 'repeat(2, min-content)',
    whiteSpace: 'nowrap',
  },
  minMaxAvgValue: { fontWeight: 600 },
  normal: {
<<<<<<< HEAD
    color: theme.palette.text.primary,
=======
    color: alpha(theme.palette.common.black, 0.6),
>>>>>>> centreon/dev-21.10.x
  },
  toggable: {
    cursor: 'pointer',
  },
}));

interface Props {
  base: number;
  displayCompleteGraph?: () => void;
<<<<<<< HEAD
  displayTimeValues: boolean;
=======
>>>>>>> centreon/dev-21.10.x
  limitLegendRows?: boolean;
  lines: Array<Line>;
  onClearHighlight: () => void;
  onHighlight: (metric: string) => void;
  onSelect: (metric: string) => void;
  onToggle: (metric: string) => void;
<<<<<<< HEAD
  timeSeries: Array<TimeValue>;
  toggable: boolean;
}

=======
  toggable: boolean;
}

type LegendContentProps = Props & Pick<ResourceContext, 'panelWidth'>;

>>>>>>> centreon/dev-21.10.x
interface GetMetricValueProps {
  unit: string;
  value: number | null;
}

const LegendContent = ({
  lines,
  onToggle,
  onSelect,
  toggable,
  onHighlight,
  onClearHighlight,
<<<<<<< HEAD
  base,
  limitLegendRows = false,
  displayCompleteGraph,
  timeSeries,
  displayTimeValues,
}: Props): JSX.Element => {
  const panelWidth = useAtomValue(panelWidthStorageAtom);
  const classes = useStyles({ limitLegendRows, panelWidth });
  const theme = useTheme();
  const { t } = useTranslation();
  const timeValue = useAtomValue(timeValueAtom);

  const graphTimeValue = timeSeries.find((timeSerie) =>
    equals(timeSerie.timeTick, timeValue?.timeTick),
  );
=======
  panelWidth,
  base,
  limitLegendRows = false,
  displayCompleteGraph,
}: LegendContentProps): JSX.Element => {
  const classes = useStyles({ limitLegendRows, panelWidth });
  const theme = useTheme();
  const { metricsValue, getFormattedMetricData } = useMetricsValueContext();
  const { t } = useTranslation();
>>>>>>> centreon/dev-21.10.x

  const getLegendName = ({ legend, name, unit }: Line): JSX.Element => {
    const legendName = legend || name;
    const unitName = ` (${unit})`;
    const metricName = includes('#', legendName)
      ? split('#')(legendName)[1]
      : legendName;

    return (
      <div>
        <Tooltip placement="top" title={legendName + unitName}>
          <Typography
            className={classes.legendName}
            component="p"
            variant="caption"
          >
            {metricName}
          </Typography>
        </Tooltip>
      </div>
    );
  };

<<<<<<< HEAD
  const getMetricsToDisplay = (): Array<string> => {
    if (isNil(graphTimeValue)) {
      return [];
    }
    const metrics = getMetrics(graphTimeValue as TimeValue);

    const metricsToDisplay = metrics.filter((metric) => {
      const line = getLineForMetric({ lines, metric });

      return !isNil(graphTimeValue[metric]) && !isNil(line);
    });

    return metricsToDisplay;
  };

=======
>>>>>>> centreon/dev-21.10.x
  const getMetricValue = ({ value, unit }: GetMetricValueProps): string =>
    formatMetricValue({
      base,
      unit,
      value,
    }) || 'N/A';

<<<<<<< HEAD
  const getFormattedMetricData = (
    metric: string,
  ): FormattedMetricData | null => {
    if (isNil(graphTimeValue)) {
      return null;
    }
    const value = graphTimeValue[metric] as number;

    const { color, name, unit } = getLineForMetric({
      lines,
      metric,
    }) as Line;

    const formattedValue = formatMetricValue({
      base,
      unit,
      value,
    });

    return {
      color,
      formattedValue,
      name,
      unit,
    };
  };

=======
>>>>>>> centreon/dev-21.10.x
  const displayedLines = limitLegendRows
    ? slice(0, maxLinesDisplayed, lines)
    : lines;

  const hasMoreLines = limitLegendRows && gt(length(lines), maxLinesDisplayed);

<<<<<<< HEAD
  const metrics = getMetricsToDisplay();

=======
>>>>>>> centreon/dev-21.10.x
  return (
    <div className={classes.legend}>
      <div>
        <div className={classes.items}>
          {displayedLines.map((line) => {
            const {
              color,
              name,
              display,
              metric: metricLine,
              highlight,
            } = line;

            const markerColor = display
              ? color
              : alpha(theme.palette.text.disabled, 0.2);

<<<<<<< HEAD
            const metric = find(equals(line.metric), metrics);

            const formattedValue =
              displayTimeValues &&
              metric &&
              getFormattedMetricData(metric)?.formattedValue;
=======
            const metric = find(
              equals(line.metric),
              propOr([], 'metrics', metricsValue),
            );

            const formattedValue =
              metric && getFormattedMetricData(metric)?.formattedValue;
>>>>>>> centreon/dev-21.10.x

            const minMaxAvg = [
              {
                label: labelMin,
                value: line.minimum_value,
              },
              {
                label: labelMax,
                value: line.maximum_value,
              },
              {
                label: labelAvg,
                value: line.average_value,
              },
            ];

<<<<<<< HEAD
            const selectMetricLine = (event: MouseEvent): void => {
=======
            const selectMetricLine = (event: React.MouseEvent): void => {
>>>>>>> centreon/dev-21.10.x
              if (!toggable) {
                return;
              }

              if (event.ctrlKey || event.metaKey) {
                onToggle(metricLine);

                return;
              }

              onSelect(metricLine);
            };

            return (
              <Box
                className={clsx(
                  classes.item,
                  highlight ? classes.highlight : classes.normal,
                  toggable && classes.toggable,
                )}
                key={name}
                onClick={selectMetricLine}
                onMouseEnter={(): void => onHighlight(metricLine)}
                onMouseLeave={(): void => onClearHighlight()}
              >
                <LegendMarker color={markerColor} disabled={!display} />
                <div className={classes.legendData}>
                  <div className={classes.legendName}>
                    {getLegendName(line)}
                    <Typography
                      className={classes.legendUnit}
                      component="p"
                      variant="caption"
                    >
                      {`(${line.unit})`}
                    </Typography>
                  </div>
                  {formattedValue ? (
                    <Typography className={classes.legendValue} variant="h6">
                      {formattedValue}
                    </Typography>
                  ) : (
                    <div className={classes.minMaxAvgContainer}>
                      {minMaxAvg.map(({ label, value }) => (
                        <div aria-label={t(label)} key={label}>
                          <Typography variant="caption">
                            {t(label)}:{' '}
                          </Typography>
                          <Typography
                            className={classes.minMaxAvgValue}
                            variant="caption"
                          >
                            {getMetricValue({
                              unit: line.unit,
                              value,
                            })}
                          </Typography>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </Box>
            );
          })}
        </div>
        {hasMoreLines && (
          <Button
            fullWidth
            color="primary"
            size="small"
            onClick={displayCompleteGraph}
          >
            <BarChartIcon fontSize="small" />
            {t(labelDisplayCompleteGraph)}
          </Button>
        )}
      </div>
    </div>
  );
};

<<<<<<< HEAD
const memoProps = [
  'panelWidth',
  'lines',
  'toggable',
  'timeSeries',
  'displayTimeValues',
  'base',
];

const MemoizedLegendContent = memoizeComponent<Props>({
=======
const memoProps = ['panelWidth', 'lines', 'toggable'];

const MemoizedLegendContent = memoizeComponent<LegendContentProps>({
>>>>>>> centreon/dev-21.10.x
  Component: LegendContent,
  memoProps,
});

const Legend = (props: Props): JSX.Element => {
<<<<<<< HEAD
  return <MemoizedLegendContent {...props} />;
=======
  const { panelWidth } = useResourceContext();

  return <MemoizedLegendContent {...props} panelWidth={panelWidth} />;
>>>>>>> centreon/dev-21.10.x
};

export default Legend;
