import { useRefreshInterval } from '@centreon/ui';

import { equals, includes } from 'ramda';

import { getResourcesUrl } from '../../utils';
import Chart from './Chart/Chart';
import { DisplayType, StatusChartProps } from './models';
import { useStyles } from './StatusChart.styles';
import { labelHosts, labelServices } from './translatedLabels';

const StatusChart = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount,
  id,
  dashboardId,
  playlistHash,
  widgetPrefixQuery
}: StatusChartProps): JSX.Element => {
  const { cx, classes } = useStyles();

  const {
    displayType,
    refreshInterval,
    refreshIntervalCustom,
    displayLegend,
    displayValues,
    resourceTypes,
    unit,
    stateList
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
            stateList={stateList}
            dashboardId={dashboardId}
            displayLegend={displayLegend}
            displayType={displayType}
            displayValues={displayValues}
            getLinkToResourceStatusPage={getLinkToResourceStatusPage}
            id={id}
            isHorizontalBar={isHorizontalBar}
            isSingleChart={isSingleChart}
            key={resourceType}
            playlistHash={playlistHash}
            refreshCount={refreshCount}
            refreshIntervalToUse={refreshIntervalToUse}
            resources={resources}
            resourceType={resourceType}
            resourceTypes={resourceTypes}
            title={isOfTypeHost ? labelHosts : labelServices}
            unit={unit}
            widgetPrefixQuery={widgetPrefixQuery}
          />
        );
      })}
    </div>
  );
};

export default StatusChart;
