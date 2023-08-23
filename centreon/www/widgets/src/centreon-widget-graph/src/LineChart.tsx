import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { LineChart, useGraphQuery, useRefreshInterval } from '@centreon/ui';

import { Data, PanelOptions } from './models';
import { labelNoDataFound } from './translatedLabels';
import { useNoDataFoundStyles } from './NoDataFound.styles';
import { graphEndpoint } from './api/endpoints';
import useThresholds from './useThresholds';

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

  const { graphData, start, end, isGraphLoading, isMetricIdsEmpty } =
    useGraphQuery({
      baseEndpoint: graphEndpoint,
      metrics: panelData.metrics,
      refreshInterval: refreshIntervalToUse,
      timePeriod: panelOptions.timeperiod?.timePeriodType
    });

  const { thresholdLabels, thresholdValues } = useThresholds({
    data: graphData,
    metricName: panelData.metrics[0]?.metrics[0]?.name,
    thresholds: panelOptions.threshold
  });

  if (isNil(graphData) && (!isGraphLoading || isMetricIdsEmpty)) {
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
      thresholdLabels={thresholdLabels}
      thresholdUnit={panelData.metrics[0]?.metrics[0]?.unit}
      thresholds={thresholdValues}
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
