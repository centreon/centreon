import dayjs from 'dayjs';
import { dec, equals, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Box,
  CircularProgress,
  Divider,
  Typography,
  useTheme
} from '@mui/material';

import { SeverityCode, useLocaleDateTimeFormat } from '@centreon/ui';

import { useHostTooltipContentStyles } from '../StatusGrid.styles';
import { ResourceData } from '../models';
import {
  labelAllServicesAreWorkingFine,
  labelServiceName
} from '../translatedLabels';
import { getColor } from '../utils';

import States from './States';
import { useHostTooltipContent } from './useHostTooltipContent';

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

  const statusOk = equals(data.status, SeverityCode.OK);

  const mentionStatus =
    equals(data.status, SeverityCode.Low) ||
    equals(data.status, SeverityCode.None);

  return (
    <Box>
      <Box className={classes.header}>
        <Typography
          data-resourceName={data.name}
          sx={{
            color: getColor({ severityCode: data.status, theme })
          }}
        >
          <strong>{data.name}</strong>
        </Typography>
      </Box>
      <Box className={classes.body}>
        {mentionStatus && (
          <Typography className={classes.listContainer}>
            {data.statusName} {data.information}
          </Typography>
        )}
        <States data={data} />
        <Box className={classes.listContainer}>
          {hasServices && (
            <div>
              <Typography className={classes.listHeader}>
                <strong>{t(labelServiceName)}</strong>
              </Typography>
              {services.map(({ name, status }, index) => {
                const isLastElement = equals(dec(services.length), index);

                return (
                  <Typography
                    data-serviceName={name}
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
          {!hasServices && !isLoading && statusOk && (
            <Typography color="text.secondary">
              {t(labelAllServicesAreWorkingFine)}
            </Typography>
          )}
          {isLoading && <CircularProgress size={24} />}
        </Box>
        <Divider variant="fullWidth" />
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
