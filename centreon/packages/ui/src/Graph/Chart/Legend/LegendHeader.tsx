import { Typography } from '@mui/material';

import {
  EllipsisTypography,
  formatMetricName,
  formatMetricValue
} from '../../..';
import { Tooltip } from '../../../components';
import { Line } from '../../common/timeSeries/models';

import { ReactElement } from 'react';
import LegendContent from './LegendContent';
import { LegendDisplayMode } from './models';

interface Props {
  isDisplayedOnSide: boolean;
  isListMode: boolean;
  line: Line;
  minMaxAvg?;
  unit: string;
  value?: string | null;
}

const LegendHeader = ({
  line,
  value,
  minMaxAvg,
  isListMode,
  isDisplayedOnSide,
  unit
}: Props): ReactElement => {
  const { name, legend } = line;

  const metricName = formatMetricName({ legend, name });

  const legendName = legend || name;

  return (
    <div className={isListMode ? 'w-fit' : 'w-full'}>
      <Tooltip
        followCursor={false}
        label={
          minMaxAvg ? (
            <div>
              <Typography>{legendName}</Typography>
              <div className="flex flex-wrap gap-1 whitespace-nowrap">
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
        <div className="flex items-center gap-1">
          <EllipsisTypography
            className="text-xs leading-none font-medium"
            containerClassname={`w-auto ${(!isListMode || (isListMode && isDisplayedOnSide)) && 'max-w-[166px]'}`}
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
