import dayjs from 'dayjs';

import { ComponentColumnProps, useLocaleDateTimeFormat } from '@centreon/ui';
import { Typography } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { labelNeverExpire } from '../../../translatedLabels';
import useStyles from './ExpirationDate.styles';

const dateFormat = 'L';

const ExpirationDate = ({
  row,
  isHovered
}: ComponentColumnProps): JSX.Element => {
  const isExpired = dayjs(dayjs(row.expirationDate)).isBefore(dayjs());

  const { classes } = useStyles({
    isExpired,
    isHovered
  });
  const { format } = useLocaleDateTimeFormat();
  const { t } = useTranslation();

  const expirationDate = row.expirationDate
    ? format({
        date: row.expirationDate,
        formatString: dateFormat
      })
    : t(labelNeverExpire);

  return (
    <Typography className={classes.container}>{expirationDate}</Typography>
  );
};

export default ExpirationDate;
