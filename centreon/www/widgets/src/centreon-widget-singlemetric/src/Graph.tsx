import { T, always, cond, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { Gauge, SingleBar, useGraphQuery } from '@centreon/ui';

import { FormThreshold, ServiceMetric } from './models';
import { labelNoDataFound } from './translatedLabels';
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
          thresholdTooltipLabels={thresholdLabels}
          thresholds={thresholdValues}
        />
      )
    ],
    [T, always(<Typography>Text</Typography>)]
  ])(singleMetricGraphType);
};

export default Graph;
