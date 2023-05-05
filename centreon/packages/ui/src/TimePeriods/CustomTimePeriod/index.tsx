import { MouseEvent, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';

import {
  changeCustomTimePeriodDerivedAtom,
  customTimePeriodAtom
} from '../timePeriodsAtoms';

import CompactCustomTimePeriod from './CompactCustomTimePeriod';
import PopoverCustomTimePeriod from './PopoverCustomTimePeriod';

interface Props {
  width: number;
}

const CustomTimePeriod = ({ width }: Props): JSX.Element => {
  const [anchorEl, setAnchorEl] = useState<HTMLButtonElement>();

  const customTimePeriod = useAtomValue(customTimePeriodAtom);
  const changeCustomTimePeriod = useSetAtom(changeCustomTimePeriodDerivedAtom);

  const acceptDate = ({ property, date }): void =>
    changeCustomTimePeriod({ date, property });

  const onClickCompactCustomTimePeriod = (
    event: MouseEvent<HTMLButtonElement>
  ): void => {
    setAnchorEl(event.currentTarget);
  };

  const closePopover = (): void => {
    setAnchorEl(undefined);
  };

  return (
    <>
      <CompactCustomTimePeriod
        width={width}
        onClick={onClickCompactCustomTimePeriod}
      />
      <PopoverCustomTimePeriod
        pickersData={{
          acceptDate,
          customTimePeriod,
          rangeEndDate: { min: customTimePeriod.start },
          rangeStartDate: { max: customTimePeriod.end }
        }}
        popoverData={{
          anchorEl,
          onClose: closePopover,
          open: Boolean(anchorEl)
        }}
      />
    </>
  );
};

export default CustomTimePeriod;
