import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';
import dayjs from 'dayjs';

import { Typography } from '@mui/material';
import {
  DesktopDateTimePicker,
  LocalizationProvider
} from '@mui/x-date-pickers';

import { SelectField, useDateTimePickerAdapter } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import {
  labelEnd,
  labelStart,
  labelTimePeriod,
  labelTo
} from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';

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

  const { Adapter } = useDateTimePickerAdapter();
  const { locale, timezone } = useAtomValue(userAtom);

  return (
    <div className={classes.container}>
      <Typography>
        <strong>{t(labelTimePeriod)}</strong>
      </Typography>
      <SelectField
        dataTestId={labelTimePeriod}
        options={options}
        selectedOptionId={value.timePeriodType || ''}
        onChange={setTimePeriod}
      />
      {isCustomizeTimePeriod && (
        <div className={classes.customTimePeriod}>
          <LocalizationProvider
            adapterLocale={locale.substring(0, 2)}
            dateAdapter={Adapter}
          >
            <DesktopDateTimePicker
              label=""
              maxDate={dayjs(value.end).tz(timezone)}
              slotProps={{
                textField: {
                  'aria-label': labelStart
                }
              }}
              value={dayjs(value.start).tz(timezone)}
              viewRenderers={{
                hours: null,
                minutes: null,
                seconds: null
              }}
              onChange={changeCustomDate('start')}
            />
            {t(labelTo)}
            <DesktopDateTimePicker
              label=""
              minDate={dayjs(value.start).tz(timezone)}
              slotProps={{
                textField: {
                  'aria-label': labelEnd
                }
              }}
              value={dayjs(value.end).tz(timezone)}
              viewRenderers={{
                hours: null,
                minutes: null,
                seconds: null
              }}
              onChange={changeCustomDate('end')}
            />
          </LocalizationProvider>
        </div>
      )}
    </div>
  );
};

export default TimePeriod;
