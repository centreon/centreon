import { MouseEvent, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';

import { useDebounce } from '@centreon/ui';

import {
  changeCustomTimePeriodDerivedAtom,
  customTimePeriodAtom
} from '../timePeriodsAtoms';

import CompactCustomTimePeriod from './CompactCustomTimePeriod';
import PopoverCustomTimePeriod from './PopoverCustomTimePeriod';

interface Props {
  disabled?: boolean;
  isCondensed?: boolean;
}

const CustomTimePeriod = ({
  disabled = false,
  isCondensed = false
}: Props): JSX.Element => {
  const [anchorEl, setAnchorEl] = useState<HTMLButtonElement>();

  const customTimePeriod = useAtomValue(customTimePeriodAtom);
  const changeCustomTimePeriod = useSetAtom(changeCustomTimePeriodDerivedAtom);

  const debouncedChangeDate = useDebounce({
    functionToDebounce: ({ property, date }): void =>
      changeCustomTimePeriod({ date, property }),
    wait: 500
  });

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
        disabled={disabled}
        isCondensed={isCondensed}
        onClick={onClickCompactCustomTimePeriod}
      />
      <PopoverCustomTimePeriod
        pickersData={{
          acceptDate: debouncedChangeDate,
          customTimePeriod,
          isDisabledEndPicker: disabled,
          isDisabledStartPicker: disabled,
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
