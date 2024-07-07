import { equals, isEmpty, isNotNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import dayjs from 'dayjs';

import {
  Box,
  CircularProgress,
  Divider,
  Typography,
  useTheme
} from '@mui/material';

import {
  useLocaleDateTimeFormat,
  usePluralizedTranslation
} from '@centreon/ui';

import { CalculationMethodType, ResourceData } from '../models';
import { useHostTooltipContentStyles } from '../StatusGrid.styles';
import { getColor } from '../utils';
import {
  criticalThreshold,
  labelAllKPIsAreWorkingFine,
  labelAreWorkingFine,
  labelCalculationMethod,
  labelStateInformation,
  warningThreshold
} from '../translatedLabels';

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
    isPercentage
  } = useBATooltipContent(data);

  const hasIndicatorsWithProblems =
    isNotNil(indicatorsWithProblems) && !isEmpty(indicatorsWithProblems);

  const areAllIndicatorsOk = equals(indicatorsWithStatusOk?.length, total);
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
        <Box className={classes.listContainer}>
          {isLoading && <CircularProgress size={24} />}
          {(isImpact || isRatio) && (
            <Box mb={1}>
              <Box className={classes.statusInformation}>
                <Typography>
                  <strong>{t(labelStateInformation)}</strong>
                </Typography>
              </Box>
              <Box className={classes.thresholdContainer} mt={1}>
                <Box className={classes.threshold}>
                  <Typography variant="body2">{t(warningThreshold)}</Typography>
                  <Typography
                    sx={{
                      color: getColor({
                        severityCode: 2,
                        theme
                      })
                    }}
                    variant="body2"
                  >
                    {formatThreshold(warningLevel)}
                  </Typography>
                </Box>

                <Box className={classes.threshold}>
                  <Typography variant="body2">
                    {t(criticalThreshold)}
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
                    {formatThreshold(criticalLevel)}
                  </Typography>
                </Box>
              </Box>
            </Box>
          )}

          <Divider variant="fullWidth" />

          {hasIndicatorsWithProblems && (
            <Box mt={1}>
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

          {areAllIndicatorsOk && (
            <Typography color="text.secondary" mt={1} variant="body2">
              {t(labelAllKPIsAreWorkingFine)}
            </Typography>
          )}

          {!areAllIndicatorsOk && hasIndicatorsWithProblems && (
            <Typography color="text.secondary" mt={1} variant="body2">
              {`${indicatorsWithStatusOk?.length}/${total} KPIs ${t(labelAreWorkingFine)}`}
            </Typography>
          )}

          {/* {infrastructureViewId && (
            <Typography variant="body2">
              <Link
                aria-label={t(labelSeeMoreInGeoview)}
                className={classes.link}
                target="_blank"
                to={`/monitoring/map/view/${infrastructureViewId}`}
              >
                {t(labelSeeMoreInGeoview)}
              </Link>
            </Typography>
          )} */}
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

export default BATooltipContent;
