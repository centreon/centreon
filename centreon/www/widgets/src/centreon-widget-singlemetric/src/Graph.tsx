import { T, always, cond, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { Gauge, GraphText, SingleBar, useGraphQuery } from '@centreon/ui';

import { FormThreshold, ServiceMetric } from './models';
import {
  labelCritical,
  labelNoDataFound,
  labelWarning
} from './translatedLabels';
import { useNoDataFoundStyles } from './NoDataFound.styles';
import { graphEndpoint } from './api/endpoints';
import useThresholds from './useThresholds';

interface Props {
  metrics: Array<ServiceMetric>;
  singleMetricGraphType: 'text' | 'gauge' | 'bar';
  threshold: FormThreshold;
}

const Graph = ({
  metrics,
  singleMetricGraphType,
  threshold
}: Props): JSX.Element => {
  const { classes } = useNoDataFoundStyles();
  const { t } = useTranslation();
  const { graphData, isGraphLoading, isMetricIdsEmpty } = useGraphQuery({
    baseEndpoint: graphEndpoint,
    metrics
  });

  const { thresholdLabels, thresholdValues } = useThresholds({
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

  return cond([
    [
      equals('gauge'),
      always(
        <Gauge
          data={graphData}
          disabledThresholds={!threshold.enabled}
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
          disabledThresholds={!threshold.enabled}
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
          disabledThresholds={!threshold.enabled}
          labels={{
            critical: t(labelCritical),
            warning: t(labelWarning)
          }}
          thresholds={thresholdValues}
        />
      )
    ]
  ])(singleMetricGraphType);
};

export default Graph;
