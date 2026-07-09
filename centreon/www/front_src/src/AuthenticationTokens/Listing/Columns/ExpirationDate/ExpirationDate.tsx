import { Typography } from '@mui/material';

import { ComponentColumnProps, useLocaleDateTimeFormat } from '@centreon/ui';

import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';

import { labelNeverExpire } from '../../../translatedLabels';
import useStyles from './ExpirationDate.styles';

const dateFormat = 'L LT';

const ExpirationDate = ({
  row,
  isHovered
}: ComponentColumnProps): JSX.Element => {
  const typedRow = row as { expirationDate?: string | Date | null };
  const isExpired = dayjs(dayjs(typedRow.expirationDate)).isBefore(dayjs());

  const { classes } = useStyles({
    isExpired,
    isHovered
  });
  const { format } = useLocaleDateTimeFormat();
  const { t } = useTranslation();

  const expirationDate = typedRow.expirationDate
    ? format({
        date: typedRow.expirationDate,
        formatString: dateFormat
      })
    : t(labelNeverExpire);

  return (
    <Typography className={classes.container}>{expirationDate}</Typography>
  );
};

export default ExpirationDate;
