<<<<<<< HEAD
import { createContext, useContext, useState } from 'react';

import { not } from 'ramda';

import ArrowBackIosIcon from '@mui/icons-material/ArrowBackIos';
import ArrowForwardIosIcon from '@mui/icons-material/ArrowForwardIos';

import { labelBackward, labelForward } from '../../../../translatedLabels';
import memoizeComponent from '../../../../memoizedComponent';
=======
import * as React from 'react';

import { not } from 'ramda';

import ArrowBackIosIcon from '@material-ui/icons/ArrowBackIos';
import ArrowForwardIosIcon from '@material-ui/icons/ArrowForwardIos';

import { labelBackward, labelForward } from '../../../../translatedLabels';
>>>>>>> centreon/dev-21.10.x

import TimeShiftZone, { timeShiftZoneWidth } from './Zone';
import TimeShiftIcon, { timeShiftIconSize } from './Icon';

export enum TimeShiftDirection {
  backward,
  forward,
}

interface TimeShiftContextProps {
  canAdjustTimePeriod: boolean;
  graphHeight: number;
  graphWidth: number;
  loading: boolean;
  marginLeft: number;
  marginTop: number;
  shiftTime?: (direction: TimeShiftDirection) => void;
}

<<<<<<< HEAD
export const TimeShiftContext = createContext<
=======
export const TimeShiftContext = React.createContext<
>>>>>>> centreon/dev-21.10.x
  TimeShiftContextProps | undefined
>(undefined);

export const useTimeShiftContext = (): TimeShiftContextProps =>
<<<<<<< HEAD
  useContext(TimeShiftContext) as TimeShiftContextProps;

const TimeShifts = (): JSX.Element | null => {
  const [directionHovered, setDirectionHovered] =
    useState<TimeShiftDirection | null>(null);
=======
  React.useContext(TimeShiftContext) as TimeShiftContextProps;

const TimeShifts = (): JSX.Element | null => {
  const [directionHovered, setDirectionHovered] =
    React.useState<TimeShiftDirection | null>(null);
>>>>>>> centreon/dev-21.10.x

  const { graphWidth, canAdjustTimePeriod } = useTimeShiftContext();

  const hoverDirection = (direction: TimeShiftDirection | null) => (): void =>
    setDirectionHovered(direction);

  if (not(canAdjustTimePeriod)) {
    return null;
  }

  return (
    <>
      <TimeShiftIcon
        Icon={ArrowBackIosIcon}
        ariaLabel={labelBackward}
        direction={TimeShiftDirection.backward}
        directionHovered={directionHovered}
        xIcon={0}
      />
      <TimeShiftIcon
        Icon={ArrowForwardIosIcon}
        ariaLabel={labelForward}
        direction={TimeShiftDirection.forward}
        directionHovered={directionHovered}
        xIcon={graphWidth + timeShiftZoneWidth + timeShiftIconSize}
      />
      <TimeShiftZone
        direction={TimeShiftDirection.backward}
        directionHovered={directionHovered}
        onDirectionHover={hoverDirection}
      />
      <TimeShiftZone
        direction={TimeShiftDirection.forward}
        directionHovered={directionHovered}
        onDirectionHover={hoverDirection}
      />
    </>
  );
};

<<<<<<< HEAD
export default memoizeComponent({ Component: TimeShifts, memoProps: [] });
=======
export default TimeShifts;
>>>>>>> centreon/dev-21.10.x
