import { Dispatch, SetStateAction } from 'react';

import { Shape } from '@visx/visx';
import { equals, negate } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { alpha, useTheme } from '@mui/material';

import { GraphInterval, Interval } from '../../models';

import { TimeShiftDirection } from './models';
import { useTimeShiftZones } from './useTimeShiftZones';

export const timeShiftZoneWidth = 50;

const useStyles = makeStyles()({
  translationZone: {
    cursor: 'pointer'
  }
});

interface Props {
  direction: TimeShiftDirection;
  directionHovered: TimeShiftDirection | null;
  getInterval?: (args: Interval) => void;
  graphHeight: number;
  graphInterval: GraphInterval;
  graphWidth: number;
  onDirectionHover: Dispatch<SetStateAction<TimeShiftDirection | null>>;
}

const TimeShiftZone = ({
  direction,
  onDirectionHover,
  directionHovered,
  graphHeight,
  graphWidth,
  graphInterval,
  getInterval
}: Props): JSX.Element => {
  const theme = useTheme();
  const { classes } = useStyles();

  const { end, start } = useTimeShiftZones({
    direction,
    graphInterval
  });

  const handleClick = (): void => getInterval?.({ end, start });

  return (
    <Shape.Bar
      className={classes.translationZone}
      fill={
        equals(directionHovered, direction)
          ? alpha(theme.palette.background.paper, 0.2)
          : 'transparent'
      }
      height={graphHeight}
      width={timeShiftZoneWidth}
      x={
        equals(direction, TimeShiftDirection.backward)
          ? negate(timeShiftZoneWidth)
          : graphWidth
      }
      y={0}
      onClick={handleClick}
      onMouseLeave={(): void => onDirectionHover(null)}
      onMouseOver={(): void => onDirectionHover(direction)}
    />
  );
};

export default TimeShiftZone;
