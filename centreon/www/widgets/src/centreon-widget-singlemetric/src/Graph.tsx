import { T, always, cond, equals, isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import {
  Gauge,
  GraphText,
  SingleBar,
  useGraphQuery,
  useRefreshInterval
} from '@centreon/ui';

import { FormThreshold, ServiceMetric, ValueFormat } from './models';
import {
  labelCritical,
  labelNoDataFound,
  labelWarning
} from './translatedLabels';
import { useNoDataFoundStyles } from './NoDataFound.styles';
import { graphEndpoint } from './api/endpoints';
import useThresholds from './useThresholds';
import { useGraphStyles } from './Graph.styles';

interface Props {
  globalRefreshInterval?: number;
  metrics: Array<ServiceMetric>;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  singleMetricGraphType: 'text' | 'gauge' | 'bar';
  threshold: FormThreshold;
  valueFormat: ValueFormat;
}

const Graph = ({
  metrics,
  singleMetricGraphType,
  threshold,
  refreshInterval,
  refreshIntervalCustom,
  globalRefreshInterval,
  valueFormat
}: Props): JSX.Element => {
  const { classes } = useNoDataFoundStyles();
  const { classes: graphClasses } = useGraphStyles();

  const { t } = useTranslation();

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const { graphData, isGraphLoading, isMetricIdsEmpty } = useGraphQuery({
    baseEndpoint: graphEndpoint,
    metrics,
    refreshInterval: refreshIntervalToUse
  });

  const displayAsRaw = equals('raw')(valueFormat);

  const { thresholdLabels, thresholdValues } = useThresholds({
    data: graphData,
    displayAsRaw,
    metricName: metrics[0]?.metrics[0]?.name,
    thresholds: threshold
  });

  if (isNil(graphData) && (!isGraphLoading || isMetricIdsEmpty)) {
    return (
      <Typography className={classes.noDataFound} variant="h5">
        {t(labelNoDataFound)}
      </Typography>
    );
  }

  const disabledThresholds = !threshold.enabled || isEmpty(thresholdValues);

  return (
    <Box className={graphClasses.graphContainer}>
      <Typography className={graphClasses.title} variant="h6">
        {metrics[0]?.name}: {metrics[0]?.metrics[0]?.name}
      </Typography>
      <Box className={graphClasses.content}>
        {cond([
          [
            equals('gauge'),
            always(
              <Gauge
                data={graphData}
                disabledThresholds={disabledThresholds}
                displayAsRaw={displayAsRaw}
                thresholdTooltipLabels={thresholdLabels}
                thresholds={thresholdValues}
              />
            )
          ],
          [
            equals('bar'),
            always(
              <SingleBar
                data={graphData}
                disabledThresholds={disabledThresholds}
                displayAsRaw={displayAsRaw}
                thresholdTooltipLabels={thresholdLabels}
                thresholds={thresholdValues}
              />
            )
          ],
          [
            T,
            always(
              <GraphText
                data={graphData}
                disabledThresholds={disabledThresholds}
                displayAsRaw={displayAsRaw}
                labels={{
                  critical: t(labelCritical),
                  warning: t(labelWarning)
                }}
                thresholds={thresholdValues}
              />
            )
          ]
        ])(singleMetricGraphType)}
      </Box>
    </Box>
  );
};

export default Graph;
