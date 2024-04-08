import { Typography } from '@mui/material';

import {
  EllipsisTypography,
  formatMetricName,
  formatMetricValue
} from '../../..';
import { Line } from '../../common/timeSeries/models';
import { Tooltip } from '../../../components';

import { useLegendHeaderStyles } from './Legend.styles';
import { LegendDisplayMode } from './models';
import LegendContent from './LegendContent';

interface Props {
  color: string;
  disabled?: boolean;
  line: Line;
  minMaxAvg?;
  value?: string | null;
}

const LegendHeader = ({
  line,
  color,
  disabled,
  value,
  minMaxAvg
}: Props): JSX.Element => {
  const { classes, cx } = useLegendHeaderStyles({ color });

  const { unit, name, legend } = line;

  const metricName = formatMetricName({ legend, name });

  const legendName = legend || name;
  const unitName = `(${unit})`;

  return (
    <Tooltip
      followCursor={false}
      label={
        minMaxAvg ? (
          <div>
            <Typography>{`${legendName} ${unitName}`}</Typography>
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
          `${legendName} ${unitName}`
        )
      }
      placement="top"
    >
      <div className={classes.markerAndLegendName}>
        <div className={cx(classes.icon, { [classes.disabled]: disabled })} />
        <EllipsisTypography
          className={classes.text}
          data-mode={
            value ? LegendDisplayMode.Compact : LegendDisplayMode.Normal
          }
          variant="body2"
        >
          {metricName}
        </EllipsisTypography>
      </div>
    </Tooltip>
  );
};

export default LegendHeader;
