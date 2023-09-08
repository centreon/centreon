import { T, always, cond, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import {
  Gauge,
  GraphText,
  SingleBar,
  useGraphQuery,
  useRefreshInterval
} from '@centreon/ui';

import useThresholds from '../../useThresholds';

import { FormThreshold, ServiceMetric } from './models';
import {
  labelCritical,
  labelNoDataFound,
  labelWarning
} from './translatedLabels';
import { useNoDataFoundStyles } from './NoDataFound.styles';
import { graphEndpoint } from './api/endpoints';
import { useGraphStyles } from './Graph.styles';

interface Props {
  globalRefreshInterval?: number;
  metrics: Array<ServiceMetric>;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  singleMetricGraphType: 'text' | 'gauge' | 'bar';
  threshold: FormThreshold;
}

const Graph = ({
  metrics,
  singleMetricGraphType,
  threshold,
  refreshInterval,
  refreshIntervalCustom,
  globalRefreshInterval
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

  const formattedThresholds = useThresholds({
    data: graphData,
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

  return (
    <Box className={graphClasses.graphContainer}>
      <Typography className={graphClasses.title} variant="h6">
        {metrics[0]?.name}: {metrics[0]?.metrics[0]?.name}
      </Typography>
      <Box className={graphClasses.content}>
        {cond([
          [
            equals('gauge'),
            always(<Gauge data={graphData} thresholds={formattedThresholds} />)
          ],
          [
            equals('bar'),
            always(
              <SingleBar
                data={graphData}
                disabledThresholds={!threshold.enabled}
                thresholds={formattedThresholds}
              />
            )
          ],
          [
            T,
            always(
              <GraphText
                data={graphData}
                labels={{
                  critical: t(labelCritical),
                  warning: t(labelWarning)
                }}
                thresholds={formattedThresholds}
              />
            )
          ]
        ])(singleMetricGraphType)}
      </Box>
    </Box>
  );
};

export default Graph;
