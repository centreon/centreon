import { Shape } from '@visx/visx';
import { useSetAtom } from 'jotai';
import { equals, negate } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { alpha, useTheme } from '@mui/material';

import { useMemoComponent, useTimeShiftZones } from '@centreon/ui';

import { updatedGraphIntervalAtom } from '../../ExportableGraphWithTimeline/atoms';

import { TimeShiftDirection, useTimeShiftContext } from '.';

export const timeShiftZoneWidth = 50;

const useStyles = makeStyles()({
  translationZone: {
    cursor: 'pointer'
  }
});

interface Props {
  direction: TimeShiftDirection;
  directionHovered: TimeShiftDirection | null;
  onDirectionHover: (direction: TimeShiftDirection | null) => () => void;
}

const TimeShiftZone = ({
  direction,
  onDirectionHover,
  directionHovered
}: Props): JSX.Element => {
  const theme = useTheme();
  const { classes } = useStyles();

  const { graphHeight, graphWidth, marginLeft, marginTop, end, start } =
    useTimeShiftContext();

  const { end: newEnd, start: newStart } = useTimeShiftZones({
    direction,
    graphInterval: { end, start }
  });

  const setGraphInterval = useSetAtom(updatedGraphIntervalAtom);

  const getNewInterval = (): void => {
    setGraphInterval({ end: newEnd, start: newStart });
  };

  return useMemoComponent({
    Component: (
      <Shape.Bar
        className={classes.translationZone}
        fill={
          equals(directionHovered, direction)
            ? alpha(theme.palette.common.white, 0.5)
            : 'transparent'
        }
        height={graphHeight}
        width={timeShiftZoneWidth}
        x={
          (equals(direction, TimeShiftDirection.backward)
            ? negate(timeShiftZoneWidth)
            : graphWidth) + marginLeft
        }
        y={marginTop}
        onClick={getNewInterval}
        onMouseLeave={onDirectionHover(null)}
        onMouseOver={onDirectionHover(direction)}
      />
    ),
    memoProps: [
      directionHovered,
      graphHeight,
      graphWidth,
      marginLeft,
      marginTop
    ]
  });
};

export default TimeShiftZone;
