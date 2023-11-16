import { useTranslation } from 'react-i18next';
import { dec, equals, isEmpty } from 'ramda';
import dayjs from 'dayjs';

import {
  Box,
  CircularProgress,
  Divider,
  Typography,
  useTheme
} from '@mui/material';

import { SeverityCode, useLocaleDateTimeFormat } from '@centreon/ui';

import { ResourceData } from '../models';
import { useHostTooltipContentStyles } from '../StatusGrid.styles';
import { getColor } from '../utils';
import {
  labelAllServicesAreWorkingFine,
  labelServiceName
} from '../translatedLabels';

import { useHostTooltipContent } from './useHostTooltipContent';
import State from './State';

interface Props {
  data: ResourceData;
}

const HostTooltipContent = ({ data }: Props): JSX.Element => {
  const { classes } = useHostTooltipContentStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  const { format } = useLocaleDateTimeFormat();

  const { services, elementRef, isLoading } = useHostTooltipContent({
    name: data.name
  });

  const hasServices = !isEmpty(services);

  const mentionStatus =
    equals(data.status, SeverityCode.None) ||
    equals(data.status, SeverityCode.Pending);

  return (
    <Box>
      <Box className={classes.header}>
        <Typography
          className={classes.name}
          sx={{
            color: getColor({ severityCode: data.status, theme })
          }}
        >
          <strong>{data.name}</strong>
        </Typography>
      </Box>
      <Box className={classes.body}>
        {mentionStatus && (
          <Typography className={classes.servicesContainer}>
            {data.statusName} ({data.information})
          </Typography>
        )}
        <Box className={classes.servicesContainer}>
          {data.acknowledgementEndpoint && data.is_acknowledged && (
            <State
              endpoint={data.acknowledgementEndpoint}
              type="acknowledgement"
            />
          )}
          {data.downtimeEndpoint && data.is_in_downtime && (
            <State endpoint={data.downtimeEndpoint} type="downtime" />
          )}
        </Box>
        <Box className={classes.servicesContainer}>
          {hasServices && (
            <div>
              <Typography>
                <strong>{t(labelServiceName)}</strong>
              </Typography>
              {services.map(({ name, status }, index) => {
                const isLastElement = equals(dec(services.length), index);

                return (
                  <Typography
                    key={name}
                    ref={isLastElement ? elementRef : undefined}
                    sx={{
                      color: getColor({
                        severityCode: status?.severity_code,
                        theme
                      })
                    }}
                  >
                    {name}
                  </Typography>
                );
              })}
            </div>
          )}
          {!hasServices && !isLoading && (
            <Typography color="text.secondary">
              {t(labelAllServicesAreWorkingFine)}
            </Typography>
          )}
          {isLoading && <CircularProgress size={24} />}
        </Box>
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

export default HostTooltipContent;
