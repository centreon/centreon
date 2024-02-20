import { useRef, useState } from 'react';

import { PrimitiveAtom, useAtomValue } from 'jotai';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  SingleAutocompleteField as SelectInput,
  useLocaleDateTimeFormat
} from '@centreon/ui';

import { useStyles } from '../filter.styles';

import CustomDateInput from './CustomDateInput';

interface Props {
  label: string;
  storageData: PrimitiveAtom<Date | null>;
}

const CustomizeDateInput = ({ storageData, label }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [displayingCalendar, setDisplayingCalendar] = useState(false);
  const ref = useRef<HTMLDivElement | null>(null);
  const [anchorEl, setAnchorEl] = useState<HTMLDivElement | null>(null);
  const date = useAtomValue(storageData);
  const { format } = useLocaleDateTimeFormat();

  const displayCalendar = (): void => {
    setAnchorEl(ref?.current);
    setDisplayingCalendar(true);
  };
  const closeCalendar = (): void => {
    setAnchorEl(null);
    setDisplayingCalendar(false);
  };

  const value = isNil(date)
    ? null
    : { id: 0, name: format({ date, formatString: 'LLL' }) };

  return (
    <div style={{ display: 'flex' }}>
      <SelectInput
        className={classes.input}
        getOptionItemLabel={(option) => option?.name}
        id={label.trim()}
        label={t(label)}
        open={false}
        options={[]}
        ref={ref}
        value={value}
        onOpen={displayCalendar}
      />

      {displayingCalendar && (
        <CustomDateInput
          anchorEl={anchorEl}
          storageData={storageData}
          onClose={closeCalendar}
        />
      )}
    </div>
  );
};

export default CustomizeDateInput;
