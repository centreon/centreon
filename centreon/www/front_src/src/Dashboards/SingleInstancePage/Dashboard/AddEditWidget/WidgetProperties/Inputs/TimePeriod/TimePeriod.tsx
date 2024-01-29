import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';
import dayjs from 'dayjs';

import { Typography } from '@mui/material';

import { SelectField, SimpleCustomTimePeriod } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { labelTimePeriod } from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';

import useTimePeriod from './useTimePeriod';
import { useTimePeriodStyles } from './TimePeriod.styles';

const TimePeriod = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { classes } = useTimePeriodStyles();
  const { t } = useTranslation();

  const {
    value,
    setTimePeriod,
    options,
    isCustomizeTimePeriod,
    changeCustomDate
  } = useTimePeriod(propertyName);

  const { timezone } = useAtomValue(userAtom);

  const { canEditField } = useCanEditProperties();

  const translatedOptions = options.map(({ id, name }) => ({
    id,
    name: t(name)
  }));

  return (
    <div className={classes.container}>
      <Typography>
        <strong>{t(labelTimePeriod)}</strong>
      </Typography>
      <SelectField
        dataTestId={labelTimePeriod}
        disabled={!canEditField}
        options={translatedOptions}
        selectedOptionId={value.timePeriodType || ''}
        onChange={setTimePeriod}
      />
      {isCustomizeTimePeriod && (
        <div className={classes.customTimePeriod}>
          <SimpleCustomTimePeriod
            changeDate={({ date, property }) =>
              changeCustomDate(property)(date)
            }
            endDate={dayjs(value.end).tz(timezone).toDate()}
            startDate={dayjs(value.start).tz(timezone).toDate()}
          />
        </div>
      )}
    </div>
  );
};

export default TimePeriod;
