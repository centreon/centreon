import { createContext, useContext, useState } from 'react';

import { not } from 'ramda';

import ArrowBackIosIcon from '@mui/icons-material/ArrowBackIos';
import ArrowForwardIosIcon from '@mui/icons-material/ArrowForwardIos';

import memoizeComponent from '../../../../memoizedComponent';
import { labelBackward, labelForward } from '../../../../translatedLabels';

import TimeShiftIcon, { timeShiftIconSize } from './Icon';
import TimeShiftZone, { timeShiftZoneWidth } from './Zone';

export enum TimeShiftDirection {
  backward = 0,
  forward = 1
}

interface TimeShiftContextProps {
  canAdjustTimePeriod: boolean;
  end: string;
  graphHeight: number;
  graphWidth: number;
  loading: boolean;
  marginLeft: number;
  marginTop: number;
  start: string;
}

export const TimeShiftContext = createContext<
  TimeShiftContextProps | undefined
>(undefined);

export const useTimeShiftContext = (): TimeShiftContextProps =>
  useContext(TimeShiftContext) as TimeShiftContextProps;

const TimeShifts = (): JSX.Element | null => {
  const [directionHovered, setDirectionHovered] =
    useState<TimeShiftDirection | null>(null);

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

export default memoizeComponent({ Component: TimeShifts, memoProps: [] });
