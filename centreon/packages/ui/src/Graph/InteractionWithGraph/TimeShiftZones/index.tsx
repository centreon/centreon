import { useState } from 'react';

import TimeShiftZone from './TimeShiftZone';
import { TimeShiftDirection } from './models';

const TimeShiftZones = ({
  graphHeight,
  graphWidth,
  getInterval,
  graphInterval
}: any): JSX.Element => {
  const [directionHovered, setDirectionHovered] =
    useState<TimeShiftDirection | null>(null);

  const commonData = {
    getInterval,
    graphHeight,
    graphInterval,
    graphWidth
  };

  return (
    <>
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
