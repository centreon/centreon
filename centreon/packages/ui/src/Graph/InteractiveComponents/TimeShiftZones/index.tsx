import { useState } from 'react';

import { equals, isNil, negate } from 'ramda';

import ArrowBackIosIcon from '@mui/icons-material/ArrowBackIos';
import ArrowForwardIosIcon from '@mui/icons-material/ArrowForwardIos';

import { GraphInterval, Interval } from '../../models';
import { margin } from '../../common';

import TimeShiftIcon, { timeShiftIconSize } from './TimeShiftIcon';
import TimeShiftZone from './TimeShiftZone';
import { TimeShiftDirection } from './models';

interface Props {
  getInterval?: (args: Interval) => void;
  graphHeight: number;
  graphInterval: GraphInterval;
  graphWidth: number;
}

const TimeShiftZones = ({
  graphHeight,
  graphWidth,
  getInterval,
  graphInterval
}: Props): JSX.Element => {
  const [directionHovered, setDirectionHovered] =
    useState<TimeShiftDirection | null>(null);

  const marginLeft = margin.left;

  const isBackward = equals(directionHovered, TimeShiftDirection.backward);
  const displayIcon = !isNil(directionHovered);
  const propsIcon = { color: 'primary' as const };

  const xIcon = isBackward
    ? negate(marginLeft)
    : graphWidth + timeShiftIconSize;

  const yIcon = graphHeight / 2 - timeShiftIconSize / 2;

  const Icon = isBackward ? (
    <ArrowBackIosIcon {...propsIcon} />
  ) : (
    <ArrowForwardIosIcon {...propsIcon} />
  );
  const ariaLabelIcon = isBackward ? 'labelBackward' : 'labelForward';

  const commonData = {
    getInterval,
    graphHeight,
    graphInterval,
    graphWidth
  };

  return (
    <>
      {displayIcon && (
        <TimeShiftIcon
          Icon={Icon}
          ariaLabel={ariaLabelIcon}
          xIcon={xIcon}
          yIcon={yIcon}
        />
      )}

      <TimeShiftZone
        direction={TimeShiftDirection.backward}
        directionHovered={directionHovered}
        onDirectionHover={setDirectionHovered}
        {...commonData}
      />
      <TimeShiftZone
        direction={TimeShiftDirection.forward}
        directionHovered={directionHovered}
        onDirectionHover={setDirectionHovered}
        {...commonData}
      />
    </>
  );
};

export default TimeShiftZones;
