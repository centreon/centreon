import { useMemo } from 'react';

import dayjs from 'dayjs';
import { dec, equals, isEmpty } from 'ramda';
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

import { Resource } from '../../../../models';
import { useHostTooltipContentStyles } from '../../StatusGridStandard/StatusGrid.styles';
import { getColor } from '../../StatusGridStandard/utils';
import {
  labelAreWorkingFine,
  labelBusinessActivity,
  labelNoResourceFoundWithThisStatus,
  labelStatus
} from '../translatedLabels';

import { useLoadResources } from './useLoadResources';

interface Props {
  count: number;
  isBAResourceType: boolean;
  isBVResourceType: boolean;
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
  total,
  isBVResourceType,
  isBAResourceType
}: Props): JSX.Element => {
  const { classes } = useHostTooltipContentStyles();
  const { pluralizedT } = usePluralizedTranslation();
  const { t } = useTranslation();
  const theme = useTheme();

  const { format } = useLocaleDateTimeFormat();

  const isSuccessStatus = ['ok', 'up'].includes(status);
  const hasNoResource = !count;

  const { elements, elementRef, isLoading } = useLoadResources({
    bypassRequest: isSuccessStatus || hasNoResource,
    isBAResourceType,
    isBVResourceType,
    resourceType,
    resources,
    status
  });

  const hasElements = !isEmpty(elements);

  const getResourceTypeLabel = (): string => {
    if (isBVResourceType) {
      return t(labelBusinessActivity);
    }
    if (isBAResourceType) {
      return 'KPI';
    }

    return resourceType;
  };

  const { formattedCount, formattedTotal, translatedResourceType } =
    useMemo(() => {
      return {
        formattedCount: formatMetricValue({ unit: '', value: count }),
        formattedTotal: formatMetricValue({ unit: '', value: total || 0 }),
        translatedResourceType: pluralizedT({
          count,
          label: getResourceTypeLabel()
        })
      };
    }, [count, total, resourceType]);

  return (
    <Box>
      <Box className={classes.header}>
        <Typography
          data-resourceName={status}
          fontWeight="bold"
          sx={{
            color: getColor({ severityCode, theme })
          }}
        >
          {t(labelStatus)}: {t(capitalize(status))}
        </Typography>
      </Box>
      <Box className={classes.body}>
        <Box className={classes.listContainer}>
          {hasNoResource && (
            <Typography color="disabled">
              {t(labelNoResourceFoundWithThisStatus, {
                type: getResourceTypeLabel()
              })}
            </Typography>
          )}
          {!hasNoResource && isSuccessStatus && (
            <Typography color="disabled">
              {`${formattedCount}/${formattedTotal} ${translatedResourceType} `}
              {t(labelAreWorkingFine)}
            </Typography>
          )}
          {!hasNoResource && hasElements && (
            <div>
              <Typography className={classes.listHeader}>
                <strong>
                  {formatMetricValue({ unit: '', value: count })}{' '}
                  {pluralizedT({ count, label: translatedResourceType })}
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
                      color: equals(elementStatus?.severity_code, 6)
                        ? theme.palette.text.primary
                        : getColor({
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
