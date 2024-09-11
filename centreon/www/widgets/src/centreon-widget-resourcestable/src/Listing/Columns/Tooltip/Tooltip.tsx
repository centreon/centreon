import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';

import { Box, Divider, Typography } from '@mui/material';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import {
  labelOpenTicketForHost,
  labelOpenTicketForService,
  labelOpenedOn,
  labelSubject,
  labelTicketID
} from '../../translatedLabels';

import { useTooltipStyles } from './Tooltip.styles';

interface Props {
  created_at: string;
  hasNoTicket: boolean;
  id: number;
  isHost: boolean;
  subject: string;
}

const TooltipContent = ({
  id,
  subject,
  created_at,
  hasNoTicket,
  isHost
}: Props): JSX.Element | string => {
  const { classes } = useTooltipStyles();

  const { t } = useTranslation();
  const { format } = useLocaleDateTimeFormat();

  if (!hasNoTicket) {
    return t(isHost ? labelOpenTicketForHost : labelOpenTicketForService);
  }

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
          <strong>{t(labelOpenedOn)}: </strong> {format({date : created_at , formatString : "L"})}
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
