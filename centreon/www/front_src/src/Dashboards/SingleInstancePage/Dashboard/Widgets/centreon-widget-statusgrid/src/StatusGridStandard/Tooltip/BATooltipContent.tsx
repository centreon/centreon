import dayjs from 'dayjs';
import { equals, isEmpty, isNotNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Box,
  CircularProgress,
  Divider,
  Typography,
  useTheme
} from '@mui/material';

import {
  SeverityCode,
  useLocaleDateTimeFormat,
  usePluralizedTranslation
} from '@centreon/ui';

import { useHostTooltipContentStyles } from '../StatusGrid.styles';
import { CalculationMethodType, ResourceData } from '../models';
import {
  labelAllKPIsAreWorkingFine,
  labelAreWorkingFine,
  labelCalculationMethod,
  labelCriticalKPIs,
  labelCriticalThreshold,
  labelHealth,
  labelParent,
  labelStateInformation,
  labelWarningThreshold
} from '../translatedLabels';
import { getColor } from '../utils';

import useBATooltipContent from './useBATooltipContent';

interface Props {
  data: ResourceData;
}

const BATooltipContent = ({ data }: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const theme = useTheme();

  const { classes } = useHostTooltipContentStyles();
  const { format } = useLocaleDateTimeFormat();
  const { pluralizedT } = usePluralizedTranslation();

  const {
    isLoading,
    calculationMethod,
    indicatorsWithProblems,
    indicatorsWithStatusOk,
    total,
    criticalLevel,
    warningLevel,
    isPercentage,
    criticalKPIs,
    health
  } = useBATooltipContent(data);

  const hasIndicatorsWithProblems =
    isNotNil(indicatorsWithProblems) && !isEmpty(indicatorsWithProblems);

  const areAllIndicatorsOk = equals(indicatorsWithStatusOk?.length, total);
  const statusOk = equals(data.status, SeverityCode.OK);

  const isImpact = equals(calculationMethod, CalculationMethodType.Impact);
  const isRatio = equals(calculationMethod, CalculationMethodType.Ratio);

  const formatThreshold = (threshold): string => {
    return equals(isPercentage, false)
      ? `${threshold} ${pluralizedT({
          count: threshold || 0,
          label: 'KPI'
        })}`
      : `${threshold}%`;
  };

  return (
    <Box className={classes.container}>
      <Box className={classes.header}>
        <Typography
          data-resourceName={data.name}
          sx={{
            color: getColor({ severityCode: data.status, theme })
          }}
        >
          <strong>{data.name}</strong>
        </Typography>
        <Box>
          <Typography variant="body2">
            {t(labelCalculationMethod)} : {calculationMethod}
          </Typography>
        </Box>
      </Box>

      <Box className={classes.body}>
        {data.businessActivity && (
          <Box className={classes.baParent}>
            <Typography className={classes.baParentText} variant="body1">
              <strong>{t(labelParent)}:</strong> {data.businessActivity}
            </Typography>

            <Divider variant="fullWidth" />
          </Box>
        )}
        <Box className={classes.listContainer}>
          {isLoading && <CircularProgress size={24} />}
          {(isImpact || isRatio) && (
            <Box mb={1}>
              <Box className={classes.statusInformation}>
                <Typography variant="body1">
                  <strong>{t(labelStateInformation)}</strong>
                </Typography>
              </Box>
              <Box className={classes.thresholdContainer} mt={1}>
                <Box className={classes.threshold}>
                  <Typography variant="body1">
                    {isImpact ? t(labelHealth) : t(labelCriticalKPIs)}
                  </Typography>
                  <Typography
                    sx={{
                      color: getColor({
                        severityCode: isImpact ? 5 : 1,
                        theme
                      })
                    }}
                    variant="body1"
                  >
                    {isImpact ? `${health}%` : criticalKPIs}
                  </Typography>
                </Box>

                <Box className={classes.threshold}>
                  <Typography variant="body1">
                    {t(labelWarningThreshold)}
                  </Typography>
                  <Typography
                    sx={{
                      color: getColor({
                        severityCode: 2,
                        theme
                      })
                    }}
                    variant="body1"
                  >
                    {formatThreshold(warningLevel)}
                  </Typography>
                </Box>

                <Box className={classes.threshold}>
                  <Typography variant="body1">
                    {t(labelCriticalThreshold)}
                  </Typography>
                  <Typography
                    sx={{
                      color: getColor({
                        severityCode: 1,
                        theme
                      })
                    }}
                    variant="body1"
                  >
                    {formatThreshold(criticalLevel)}
                  </Typography>
                </Box>
              </Box>
            </Box>
          )}

          {hasIndicatorsWithProblems && (
            <Box mt={1}>
              {(isImpact || isRatio) && (
                <Box mb={1}>
                  <Divider variant="fullWidth" />
                </Box>
              )}
              <Typography className={classes.listHeader}>
                <strong>KPIs</strong>
              </Typography>
              {indicatorsWithProblems?.map((indicator) => {
                const kpiWarningThreshold = indicator.impact?.warning;
                const kpiCriticalThreshold = indicator.impact?.critical;

                return (
                  <Box className={classes.threshold} key={indicator.name}>
                    <Typography
                      data-serviceName={indicator.name}
                      sx={{
                        color: isImpact
                          ? 'inherit'
                          : getColor({
                              severityCode: indicator.status.severityCode,
                              theme
                            })
                      }}
                      variant="body2"
                    >
                      {indicator.name}
                    </Typography>
                    {isImpact && (
                      <Box className={classes.impact}>
                        <Typography
                          sx={{
                            color: getColor({
                              severityCode: 2,
                              theme
                            })
                          }}
                          variant="body2"
                        >
                          {`${kpiWarningThreshold}%`}
                        </Typography>
                        <Typography
                          sx={{
                            color: getColor({
                              severityCode: 1,
                              theme
                            })
                          }}
                          variant="body2"
                        >
                          {`${kpiCriticalThreshold}%`}
                        </Typography>
                      </Box>
                    )}
                  </Box>
                );
              })}
            </Box>
          )}

          {(areAllIndicatorsOk || statusOk) && (
            <Typography color="text.secondary">
              {t(labelAllKPIsAreWorkingFine)}
            </Typography>
          )}

          {!areAllIndicatorsOk && hasIndicatorsWithProblems && (
            <Typography color="text.secondary" mt={1}>
              {`${indicatorsWithStatusOk?.length}/${total} KPIs ${t(labelAreWorkingFine)}`}
            </Typography>
          )}
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

export default BATooltipContent;
