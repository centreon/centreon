import { equals, includes } from 'ramda';

import { useRefreshInterval } from '@centreon/ui';

import { getResourcesUrl } from '../../utils';

import Chart from './Chart/Chart';
import { useStyles } from './StatusChart.styles';
import { DisplayType, StatusChartProps } from './models';
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
    >
      {resourceTypes.map((resourceType) => {
        const isOfTypeHost = includes('host', resourceType);

        return (
          <Chart
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
            resourceType={resourceType}
            resourceTypes={resourceTypes}
            resources={resources}
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
