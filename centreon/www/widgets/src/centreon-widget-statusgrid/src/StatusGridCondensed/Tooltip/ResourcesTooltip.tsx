import { dec, equals, isEmpty } from 'ramda';
import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';

import {
  Box,
  CircularProgress,
  Divider,
  Typography,
  capitalize,
  useTheme
} from '@mui/material';

import {
  SeverityCode,
  formatMetricValue,
  useLocaleDateTimeFormat,
  usePluralizedTranslation
} from '@centreon/ui';

import { useHostTooltipContentStyles } from '../../StatusGridStandard/StatusGrid.styles';
import { getColor } from '../../StatusGridStandard/utils';
import { Resource } from '../../../../models';
import {
  labelAreWorkingFine,
  labelNoResourceFoundWithThisStatus
} from '../translatedLabels';

import { useLoadResources } from './useLoadResources';

interface Props {
  count: number;
  resourceType: string;
  resources: Array<Resource>;
  severityCode: SeverityCode;
  status: string;
  total?: number;
}

const ResourcesTooltip = ({
  resources,
  resourceType,
  status,
  severityCode,
  count,
  total
}: Props): JSX.Element => {
  const { classes } = useHostTooltipContentStyles();
  const { pluralizedT } = usePluralizedTranslation();
  const { t } = useTranslation();
  const theme = useTheme();

  const { format } = useLocaleDateTimeFormat();

  const isSuccessStatus = ['ok', 'up'].includes(status);
  const hasNoResource = !count;

  const { elements, elementRef, isLoading } = useLoadResources({
    bypassRequest: !isSuccessStatus || hasNoResource,
    resourceType,
    resources,
    status
  });

  const hasElements = !isEmpty(elements);

  return (
    <Box>
      <Box className={classes.header}>
        <Typography
          data-resourceName={status}
          sx={{
            color: getColor({ severityCode, theme })
          }}
        >
          <strong>{capitalize(status)}</strong>
        </Typography>
      </Box>
      <Box className={classes.body}>
        <Box className={classes.listContainer}>
          {hasNoResource && (
            <Typography color="disabled">
              {t(labelNoResourceFoundWithThisStatus, { type: resourceType })}
            </Typography>
          )}
          {isSuccessStatus && (
            <Typography color="disabled">
              {`${formatMetricValue({ unit: '', value: count })}/${formatMetricValue({ unit: '', value: total || 0 })} ${pluralizedT({ count, label: resourceType })} `}
              {t(labelAreWorkingFine)}
            </Typography>
          )}
          {hasElements && (
            <div>
              <Typography className={classes.listHeader}>
                <strong>
                  {formatMetricValue({ unit: '', value: count })}{' '}
                  {pluralizedT({ count, label: resourceType })}
                </strong>
              </Typography>
              {elements.map(({ name, status: elementStatus }, index) => {
                const isLastElement = equals(dec(elements.length), index);

                return (
                  <Typography
                    data-serviceName={name}
                    key={name}
                    ref={isLastElement ? elementRef : undefined}
                    sx={{
                      color: getColor({
                        severityCode: elementStatus?.severity_code,
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
          {/* {!hasServices && !isLoading && statusOk && (
            <Typography color="text.secondary">
              {t(labelAllServicesAreWorkingFine)}
            </Typography>
          )} */}
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

export default ResourcesTooltip;
