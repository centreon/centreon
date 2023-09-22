import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { LineChart, useGraphQuery, useRefreshInterval } from '@centreon/ui';

import useThresholds from '../../useThresholds';
import { GlobalRefreshInterval } from '../../models';

import { Data, PanelOptions } from './models';
import { labelNoDataFound } from './translatedLabels';
import { useNoDataFoundStyles } from './NoDataFound.styles';
import { graphEndpoint } from './api/endpoints';

interface Props {
  globalRefreshInterval: GlobalRefreshInterval;
  panelData: Data;
  panelOptions: PanelOptions;
  refreshCount: number;
}

const WidgetLineChart = ({
  panelData,
  panelOptions,
  globalRefreshInterval,
  refreshCount
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
      refreshCount,
      refreshInterval: refreshIntervalToUse,
      timePeriod: panelOptions.timeperiod
    });

  const formattedThresholds = useThresholds({
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
      thresholdUnit={panelData.metrics[0]?.metrics[0]?.unit}
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
