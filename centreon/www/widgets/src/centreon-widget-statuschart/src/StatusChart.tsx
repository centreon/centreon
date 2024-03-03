import { includes } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useRefreshInterval } from '@centreon/ui';

import { getResourcesUrl } from '../../utils';

import { StatusChartProps } from './models';
import { Chart } from './Compontents';
import { useStyles } from './StatusChart.styles';
import {
  labelHosts,
  labelServices,
  labelNoHostsFound,
  labelNoServicesFound
} from './translatedLabels';

const StatusChart = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount
}: StatusChartProps): JSX.Element => {
  const {
    displayType,
    refreshInterval,
    refreshIntervalCustom,
    displayLegend,
    displayValues,
    resourceTypes,
    unit
  } = panelOptions;

  const { classes } = useStyles({ displayType });

  const { t } = useTranslation();

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
    <div className={classes.container}>
      {resourceTypes.map((resourceType) => {
        const isOfTypeHost = includes('host', resourceType);

        return (
          <Chart
            displayLegend={displayLegend}
            displayType={displayType}
            displayValues={displayValues}
            getLinkToResourceStatusPage={getLinkToResourceStatusPage}
            key={resourceType}
            labelNoDataFound={
              isOfTypeHost ? t(labelNoHostsFound) : t(labelNoServicesFound)
            }
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
