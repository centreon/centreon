import { useMemo, useState } from 'react';

import dayjs from 'dayjs';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  SingleAutocompleteField as SelectInput,
  useLocaleDateTimeFormat
} from '@centreon/ui';

import { dataDuration } from '../../../../TokenCreation/models';
import { useStyles } from '../filter.styles';
import { Property } from '../models';

import DateInput from './DateInput';

interface Props {
  dataDate;
  label: string;
  property: Property;
}

const DateFilter = ({ label, dataDate, property }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { date, setDate } = dataDate;
  const { format } = useLocaleDateTimeFormat();
  const [displayCalendar, setDisplayCalendar] = useState(false);

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
    setDisplayCalendar(true);
  };

  const handleChange = (_, item, reason): void => {
    if (equals(reason, 'clear')) {
      setDate(null);
      setDisplayCalendar(false);

      return;
    }

    if (!equals(item.id, 'customize')) {
      handleDate({ item, propertyDate: property });

      return;
    }

    handleCustomizeCase();
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
    return date ? { id: 0, name: format({ date, formatString: 'LLL' }) } : null;
  }, [date]);

  return (
    <>
      <SelectInput
        className={classes.input}
        disableClearable={false}
        getOptionItemLabel={(option) => option?.name}
        id={label.trim()}
        inputProps={{ value: currentValue?.name ?? '' }}
        label={t(label)}
        options={data}
        value={currentValue}
        onChange={handleChange}
      />
      {displayCalendar && (
        <DateInput
          dataDate={dataDate}
          label={label}
          setDisplayCalendar={setDisplayCalendar}
        />
      )}
    </>
  );
};

export default DateFilter;
