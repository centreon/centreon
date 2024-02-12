import { includes } from 'ramda';

import { useRefreshInterval } from '@centreon/ui';

import { StatusChartProps } from './models';
import Chart from './Compontents/Chart/Chart';

const hosts = [
  { color: '#88B922', label: 'Up', value: 148 },
  { color: '#999999', label: 'Down', value: 13 },
  { color: '#F7931A', label: 'UnReachable', value: 16 },
  { color: '#FF6666', label: 'Pending', value: 62 }
];

const services = [
  { color: '#88B922', label: 'Ok', value: 82 },
  { color: '#F7931A', label: 'Warning', value: 1161 },
  { color: '#999999', label: 'Unknown', value: 1735 },
  { color: '#14f122', label: 'Pending', value: 1820 },
  { color: '#FF6666', label: 'Critical', value: 11262 }
];

const StatusChart = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  //   refreshCount,
  changeViewMode,
  isFromPreview
}: StatusChartProps): JSX.Element => {
  //   const { resources } = panelData;

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

  //   const refreshIntervalToUse = useRefreshInterval({
  //     globalRefreshInterval,
  //     refreshInterval,
  //     refreshIntervalCustom
  //   });

  return (
    <div style={{ display: 'flex', justifyContent: 'space-around' }}>
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
