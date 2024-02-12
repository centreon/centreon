import { includes } from 'ramda';

import { useRefreshInterval } from '@centreon/ui';

import { StatusChartProps } from './models';
import { Chart } from './Compontents/Chart';
import { useStyles } from './StatusChart.styles';

const hosts = [
  { color: '#88B922', label: 'Up', value: 148 },
  { color: '#999999', label: 'Down', value: 13 },
  { color: '#F7931A', label: 'UnReachable', value: 16 },
  { color: '#FF6666', label: 'Pending', value: 62 }
];

const services = [
  { color: '#88B922', label: 'Ok', value: 82 },
  { color: '#F7931A', label: 'Warning', value: 112 },
  { color: '#999999', label: 'Unknown', value: 165 },
  { color: '#14f122', label: 'Pending', value: 42 },
  { color: '#FF6666', label: 'Critical', value: 18 }
];

const StatusChart = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount,
  changeViewMode,
  isFromPreview
}: StatusChartProps): JSX.Element => {
  const { resources } = panelData;

  const {
    displayType,
    refreshInterval,
    refreshIntervalCustom,
    states,
    displayLegend,
    displayValues,
    resourceType,
    unit,
    displayPredominentInformation
  } = panelOptions;

  const { classes } = useStyles({ displayType });

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  return (
    <div className={classes.container}>
      {includes('host', resourceType) && (
        <Chart
          data={hosts}
          displayLegend={displayLegend}
          displayPredominentInformation={displayPredominentInformation}
          displayType={displayType}
          displayValues={displayValues}
          resourceType={resourceType}
          states={states}
          title="hosts"
          unit={unit}
        />
      )}
      {includes('service', resourceType) && (
        <Chart
          data={services}
          displayLegend={displayLegend}
          displayPredominentInformation={displayPredominentInformation}
          displayType={displayType}
          displayValues={displayValues}
          resourceType={resourceType}
          states={states}
          title="services"
          unit={unit}
        />
      )}
    </div>
  );
};

export default StatusChart;
