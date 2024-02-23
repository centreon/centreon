import { useMemo, useState } from 'react';

import dayjs from 'dayjs';
import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { TimeFieldProps } from '@mui/x-date-pickers/TimeField';

import {
  SingleAutocompleteField as SelectInput,
  useLocaleDateTimeFormat
} from '@centreon/ui';

import { dataDuration } from '../../../../TokenCreation/models';
import { useStyles } from '../filter.styles';
import { Property } from '../models';

interface Props {
  customizedDate: null | Date;
  dataDate;
  getIsDisplayingCalendar: (value: boolean) => void;
  label: string;
  onClear;
  property: Property;
}

const CustomField = ({
  getIsDisplayingCalendar,
  label,
  customizedDate,
  dataDate,
  property,
  onClear,
  ...rest
}: Props & TimeFieldProps<Date>): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const { date, setDate } = dataDate;
  const { format } = useLocaleDateTimeFormat();
  const { InputProps } = rest;

  const handleDate = ({ item, propertyDate }): void => {
    if (equals(propertyDate, Property.in)) {
      const time = dayjs().add(item.value, item.unit);
      setDate(time.toDate());

      return;
    }
    const time = dayjs().subtract(item.value, item.unit);
    setDate(time.toDate());
  };

  const handleCustomizeCase = (): void => {
    setOpen(false);
    getIsDisplayingCalendar(true);
  };

  const handleChange = (_, item, reason): void => {
    if (equals(reason, 'clear')) {
      onClear();

      return;
    }

    if (!equals(item.id, 'customize')) {
      setOpen(false);
      handleDate({ item, propertyDate: property });

      return;
    }

    handleCustomizeCase();
  };

  const onOpen = (): void => {
    setOpen(true);
  };

  const onClose = (): void => {
    setOpen(false);
  };

  const data = useMemo(() => {
    return dataDuration.map((item) => ({
      ...item,
      name: equals(item.id, 'customize')
        ? item.name
        : `${property} ${item.name}`
    }));
  }, [property]);

  const currentValue = useMemo(() => {
    if (!isNil(customizedDate)) {
      return {
        id: 0,
        name: format({ date: customizedDate, formatString: 'LLL' })
      };
    }

    return date ? { id: 0, name: format({ date, formatString: 'LLL' }) } : null;
  }, [date, customizedDate]);

  return (
    <SelectInput
      className={classes.input}
      disableClearable={false}
      getOptionItemLabel={(option) => option?.name}
      id={label.trim()}
      inputProps={{ value: currentValue?.name ?? '' }}
      label={t(label)}
      open={open}
      options={data}
      ref={InputProps?.ref}
      value={currentValue}
      onChange={handleChange}
      onClose={onClose}
      onOpen={onOpen}
    />
  );
};

export default CustomField;
