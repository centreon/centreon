import { T, always, cond, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import { Box, Typography } from '@mui/material';

import {
  Gauge,
  GraphText,
  SingleBar,
  useGraphQuery,
  useRefreshInterval
} from '@centreon/ui';
import { refreshIntervalAtom } from '@centreon/ui-context';

import useThresholds from '../../useThresholds';

import {
  FormThreshold,
  ServiceMetric,
  ValueFormat,
  GlobalRefreshInterval
} from './models';
import {
  labelCritical,
  labelNoDataFound,
  labelWarning
} from './translatedLabels';
import { useNoDataFoundStyles } from './NoDataFound.styles';
import { graphEndpoint } from './api/endpoints';
import { useGraphStyles } from './Graph.styles';

interface Props {
  globalRefreshInterval: GlobalRefreshInterval;
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

  const platformInterval = useAtomValue(refreshIntervalAtom);

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    platformInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const { graphData, isGraphLoading, isMetricIdsEmpty } = useGraphQuery({
    baseEndpoint: graphEndpoint,
    metrics,
    refreshInterval: refreshIntervalToUse
  });

  const displayAsRaw = equals('raw')(valueFormat);

  const formattedThresholds = useThresholds({
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
                baseColor={threshold.baseColor}
                data={graphData}
                displayAsRaw={displayAsRaw}
                thresholds={formattedThresholds}
              />
            )
          ],
          [
            equals('bar'),
            always(
              <SingleBar
                baseColor={threshold.baseColor}
                data={graphData}
                displayAsRaw={displayAsRaw}
                thresholds={formattedThresholds}
              />
            )
          ],
          [
            T,
            always(
              <GraphText
                baseColor={threshold.baseColor}
                data={graphData}
                displayAsRaw={displayAsRaw}
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
