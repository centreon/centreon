import { equals, includes } from 'ramda';

import { useRefreshInterval } from '@centreon/ui';

import { getResourcesUrl } from '../../utils';

import { DisplayType, StatusChartProps } from './models';
import Chart from './Chart/Chart';
import { useStyles } from './StatusChart.styles';
import { labelHosts, labelServices } from './translatedLabels';

const StatusChart = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount
}: StatusChartProps): JSX.Element => {
  const { cx, classes } = useStyles();

  const {
    displayType,
    refreshInterval,
    refreshIntervalCustom,
    displayLegend,
    displayValues,
    resourceTypes,
    unit
  } = panelOptions;

  const isHorizontalBar = equals(displayType, DisplayType.Horizontal);
  const isSingleChart = equals(resourceTypes.length, 1);

  const { resources } = panelData;

  const refreshIntervalToUse = useRefreshInterval({
    globalRefreshInterval,
    refreshInterval,
    refreshIntervalCustom
  });

  const getLinkToResourceStatusPage = (status, resourceType): string => {
    return getResourcesUrl({
      allResources: resources,
      isForOneResource: false,
      states: [],
      statuses: [status],
      type: resourceType
    });
  };

  return (
    <div
      className={cx(classes.container, {
        [classes.flexDirectionColumns]: isHorizontalBar
      })}
      style={
        isHorizontalBar
          ? { gridTemplateRows: `repeat(${resourceTypes.length}, 1fr)` }
          : { gridTemplateColumns: `repeat(${resourceTypes.length}, 1fr)` }
      }
    >
      {resourceTypes.map((resourceType) => {
        const isOfTypeHost = includes('host', resourceType);

        return (
          <Chart
            displayLegend={displayLegend}
            displayType={displayType}
            displayValues={displayValues}
            getLinkToResourceStatusPage={getLinkToResourceStatusPage}
            isHorizontalBar={isHorizontalBar}
            isSingleChart={isSingleChart}
            key={resourceType}
            refreshCount={refreshCount}
            refreshIntervalToUse={refreshIntervalToUse}
            resourceType={resourceType}
            resourceTypes={resourceTypes}
            resources={resources}
            title={isOfTypeHost ? labelHosts : labelServices}
            unit={unit}
          />
        );
      })}
    </div>
  );
};

export default StatusChart;
