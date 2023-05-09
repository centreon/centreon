import { Box, useTheme, alpha } from '@mui/material';

import { formatMetricValue } from '../timeSeries';
import { labelMin, labelMax, labelAvg } from '../translatedLabels';
import { TimeValue, Line } from '../timeSeries/models';

import { GetMetricValueProps } from './models';
import LegendHeader from './LegendHeader';
import LegendContent from './LegendContent';
import LegendMarker from './Marker';
import InteractiveValue from './InteractiveValue';
import { useStyles } from './Legend.styles';
import useInteractiveValues from './useInteractiveValues';

interface Props {
  base: number;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}

const Legend = ({ lines, timeSeries, base }: Props): JSX.Element => {
  const { classes, cx } = useStyles({});
  const theme = useTheme();

  const { getFormattedValue } = useInteractiveValues({
    base,
    lines,
    timeSeries
  });

  const getMetricValue = ({ value, unit }: GetMetricValueProps): string =>
    formatMetricValue({
      base,
      unit,
      value
    }) || 'N/A';

  return (
    <div className={classes.legend}>
      <div className={classes.items}>
        {lines.map((line) => {
          const { color, name, display, metric: metricLine, highlight } = line;

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
            <Box className={cx(classes.item)} key={name}>
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
    </div>
  );
};

export default Legend;
