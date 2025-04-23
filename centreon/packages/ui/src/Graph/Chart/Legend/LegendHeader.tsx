import { Typography } from '@mui/material';

import {
  EllipsisTypography,
  formatMetricName,
  formatMetricValue
} from '../../..';
import { Tooltip } from '../../../components';
import { Line } from '../../common/timeSeries/models';

import { useLegendHeaderStyles } from './Legend.styles';
import LegendContent from './LegendContent';
import { LegendDisplayMode } from './models';

interface Props {
  color: string;
  disabled?: boolean;
  isDisplayedOnSide: boolean;
  isListMode: boolean;
  line: Line;
  minMaxAvg?;
  unit: string;
  value?: string | null;
}

const LegendHeader = ({
  line,
  color,
  disabled,
  value,
  minMaxAvg,
  isListMode,
  isDisplayedOnSide,
  unit
}: Props): JSX.Element => {
  const { classes, cx } = useLegendHeaderStyles({ color });

  const { name, legend } = line;

  const metricName = formatMetricName({ legend, name });

  const legendName = legend || name;

  return (
    <div
      className={cx(!isListMode ? classes.container : classes.containerList)}
    >
      <Tooltip
        followCursor={false}
        label={
          minMaxAvg ? (
            <div>
              <Typography>{legendName}</Typography>
              <div className={classes.minMaxAvgContainer}>
                {minMaxAvg.map(({ label, value: subValue }) => (
                  <LegendContent
                    data={formatMetricValue({
                      unit: line.unit,
                      value: subValue
                    })}
                    key={label}
                    label={label}
                  />
                ))}
              </div>
            </div>
          ) : (
            legendName
          )
        }
        placement={isListMode ? 'right' : 'top'}
      >
        <div className={classes.markerAndLegendName}>
          <div
            data-icon
            className={cx(classes.icon, { [classes.disabled]: disabled })}
          />
          <EllipsisTypography
            className={classes.text}
            containerClassname={cx(
              !isListMode && classes.legendName,
              isListMode && !isDisplayedOnSide && classes.textListBottom,
              isListMode && isDisplayedOnSide && classes.legendName
            )}
            data-mode={
              value ? LegendDisplayMode.Compact : LegendDisplayMode.Normal
            }
          >
            {metricName}
          </EllipsisTypography>
          <Typography sx={{ lineHeight: 1.25 }} variant="caption">
            {unit}
          </Typography>
        </div>
      </Tooltip>
    </div>
  );
};

export default LegendHeader;
