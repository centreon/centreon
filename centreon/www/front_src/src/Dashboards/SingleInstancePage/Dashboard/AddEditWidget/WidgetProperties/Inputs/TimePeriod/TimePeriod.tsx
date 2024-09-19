import { useMemo } from 'react';

import dayjs from 'dayjs';
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
dayjs.extend(isSameOrAfter);

import { Typography } from '@mui/material';

import { SelectField, SimpleCustomTimePeriod } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { labelTimePeriod } from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';

import { useTimePeriodStyles } from './TimePeriod.styles';
import useTimePeriod from './useTimePeriod';

const TimePeriod = ({
  propertyName,
  isInGroup
}: WidgetPropertyProps): JSX.Element => {
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

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div className={classes.container}>
      <Label>{t(labelTimePeriod)}</Label>
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
