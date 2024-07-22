import { useTranslation } from 'react-i18next';
import dayjs from 'dayjs';

import { Box, Divider, Typography } from '@mui/material';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import {
  labelTicketID,
  labelSubject,
  labelOpenTime
} from '../../translatedLabels';

import { useTooltipStyles } from './Tooltip.styles';

interface Props {
  created_at: string;
  id: number;
  subject: string;
}

const TooltipContent = ({ id, subject, created_at }: Props): JSX.Element => {
  const { classes } = useTooltipStyles();

  const { t } = useTranslation();
  const { format } = useLocaleDateTimeFormat();

  return (
    <Box className={classes.tooltipContainer} data-testid={`tooltip-${id}`}>
      <Box className={classes.header}>
        <Typography>
          <strong>
            {t(labelTicketID)} : {id}
          </strong>
        </Typography>
      </Box>
      <Box className={classes.body}>
        <Typography className={classes.listContainer} variant="body2">
          <strong>{t(labelSubject)}: </strong> {subject}
        </Typography>
        <Typography className={classes.listContainer} variant="body2">
          <strong>{t(labelOpenTime)}: </strong> {created_at}
        </Typography>
      </Box>

      <Box>
        <Divider variant="middle" />
        <Typography
          className={classes.dateContainer}
          color="text.secondary"
          variant="body2"
        >
          {format({ date: dayjs().toISOString(), formatString: 'LLL' })}
        </Typography>
      </Box>
    </Box>
  );
};

export default TooltipContent;
