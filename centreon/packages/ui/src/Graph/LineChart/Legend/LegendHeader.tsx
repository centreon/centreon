import { includes, isEmpty, split } from 'ramda';

import { Typography } from '@mui/material';

import { EllipsisTypography, formatMetricValue } from '../../..';
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

  const legendName = legend || name;
  const hasUnit = !isEmpty(unit);
  const unitName = `(${unit})`;
  const metricName = includes('#', legendName)
    ? split('#')(legendName)[1]
    : legendName;

  const getEndText = (): string => {
    if (value) {
      return `${value}${hasUnit ? ` ${unit}` : ''}`;
    }

    return hasUnit ? ` ${unitName}` : '';
  };

  return (
    <div className={classes.container}>
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
            className={cx(classes.text, classes.legendName)}
            data-mode={
              value ? LegendDisplayMode.Compact : LegendDisplayMode.Normal
            }
          >
            {metricName}
          </EllipsisTypography>
        </div>
      </Tooltip>
      {hasUnit && (
        <Typography className={classes.text}>{getEndText()}</Typography>
      )}
    </div>
  );
};

export default LegendHeader;
