import { equals, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';
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
  labelAllMetricsAreWorkingFine,
  labelMetricName,
  labelValue
} from '../translatedLabels';

import useServiceTooltipContent from './useServiceTooltipContent';
import States from './States';

interface Props {
  data: ResourceData;
}

const ServiceTooltipContent = ({ data }: Props): JSX.Element | null => {
  const { classes, cx } = useHostTooltipContentStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  const { problematicMetrics, isLoading } = useServiceTooltipContent(data);

  const { format } = useLocaleDateTimeFormat();

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
        <Box className={classes.parent}>
          <Box
            className={classes.dot}
            data-parentstatus={data.parentStatus}
            sx={{
              backgroundColor: getColor({
                severityCode: data.parentStatus,
                theme
              })
            }}
          />
          <Typography variant="body2">{data.parentName}</Typography>
        </Box>
      </Box>
      <Box className={classes.body}>
        {mentionStatus && (
          <Typography className={classes.listContainer}>
            {data.statusName} {data.information}
          </Typography>
        )}
        <States data={data} />
        <Box className={classes.listContainer}>
          {!isEmpty(problematicMetrics) && (
            <Box className={cx(classes.listHeader, classes.metric)}>
              <Typography className={classes.metric}>
                <strong>{t(labelMetricName)}</strong>
              </Typography>
              <Typography>
                <strong>{t(labelValue)}</strong>
              </Typography>
            </Box>
          )}
          {problematicMetrics.map(({ name, status, value }) => (
            <Box className={classes.metric} key={name}>
              <Typography className={classes.metricName} variant="body2">
                {name}
              </Typography>
              <Typography
                sx={
                  status && { color: getColor({ severityCode: status, theme }) }
                }
                variant="body2"
              >
                {value}
              </Typography>
            </Box>
          ))}
          {isLoading && <CircularProgress size={24} />}
          {statusOk && (
            <Typography color="text.secondary">
              {t(labelAllMetricsAreWorkingFine)}
            </Typography>
          )}
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

export default ServiceTooltipContent;
