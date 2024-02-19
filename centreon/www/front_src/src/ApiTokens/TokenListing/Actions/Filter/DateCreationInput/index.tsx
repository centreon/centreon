import { useRef, useState } from 'react';

import {
  SingleAutocompleteField as SelectInput,
  useLocaleDateTimeFormat
} from '@centreon/ui';

import { useStyles } from '../filter.styles';

import CustomDateInput from './CustomDateInput';

const DateCreationInput = (): JSX.Element => {
  const { classes } = useStyles();
  const [creationDate, setCreationDate] = useState('');
  const [displayingCalendar, setDisplayingCalendar] = useState(false);
  const ref = useRef<HTMLDivElement | null>(null);
  const [anchorEl, setAnchorEl] = useState<HTMLDivElement | null>(null);
  const { format } = useLocaleDateTimeFormat();

  const getCurrentDate = (date): void => {
    setCreationDate(date);
  };

  const displayCalendar = (): void => {
    setAnchorEl(ref?.current);
    setDisplayingCalendar(true);
  };
  const closeCalendar = (): void => {
    setAnchorEl(null);
    setDisplayingCalendar(false);
  };

  const value = !creationDate
    ? null
    : { id: 0, name: format({ date: creationDate, formatString: 'LLL' }) };

  return (
    <div style={{ display: 'flex' }}>
      <SelectInput
        className={classes.input}
        getOptionItemLabel={(option) => option?.name}
        id="creationDate"
        label="creation date"
        open={false}
        options={[]}
        ref={ref}
        value={value}
        onOpen={displayCalendar}
      />

      {displayingCalendar && (
        <CustomDateInput
          anchorEl={anchorEl}
          getCurrentDate={getCurrentDate}
          onClose={closeCalendar}
        />
      )}
    </div>
  );
};

export default DateCreationInput;
