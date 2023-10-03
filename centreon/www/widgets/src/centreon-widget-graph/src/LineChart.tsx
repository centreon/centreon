import { head, isNil, pluck } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { LineChart, useGraphQuery, useRefreshInterval } from '@centreon/ui';

import useThresholds from '../../useThresholds';

import { Data, PanelOptions } from './models';
import { labelNoDataFound } from './translatedLabels';
import { useNoDataFoundStyles } from './NoDataFound.styles';
import { graphEndpoint } from './api/endpoints';

interface Props {
  globalRefreshInterval?: number;
  panelData: Data;
  panelOptions: PanelOptions;
}

const WidgetLineChart = ({
  panelData,
  panelOptions,
  globalRefreshInterval
}: Props): JSX.Element => {
  const { classes } = useNoDataFoundStyles();
  const { t } = useTranslation();

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval: panelOptions.refreshInterval,
    refreshIntervalCustom: panelOptions.refreshIntervalCustom
  });

  const metricNames = pluck('name', panelData.metrics);

  const { graphData, start, end, isGraphLoading, isMetricsEmpty } =
    useGraphQuery({
      baseEndpoint: graphEndpoint,
      metrics: metricNames,
      refreshInterval: refreshIntervalToUse,
      resources: panelData.resources,
      timePeriod: panelOptions.timeperiod
    });

  const formattedThresholds = useThresholds({
    data: graphData,
    metricName: head(metricNames),
    thresholds: panelOptions.threshold
  });

  if (isNil(graphData) && (!isGraphLoading || isMetricsEmpty)) {
    return (
      <Typography className={classes.noDataFound} variant="h5">
        {t(labelNoDataFound)}
      </Typography>
    );
  }

  return (
    <LineChart
      data={graphData}
      end={end}
      height={null}
      legend={{ display: true }}
      loading={isGraphLoading}
      start={start}
      thresholdUnit={panelData.metrics[0]?.unit}
      thresholds={formattedThresholds}
      timeShiftZones={{
        enable: false
      }}
      zoomPreview={{
        enable: false
      }}
    />
  );
};

export default WidgetLineChart;
