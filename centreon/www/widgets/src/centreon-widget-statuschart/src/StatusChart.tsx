import { includes } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useRefreshInterval } from '@centreon/ui';

import { StatusChartProps } from './models';
import { Chart } from './Compontents';
import { useStyles } from './StatusChart.styles';
import { labelHosts, labelServices } from './translatedLabels';

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
    resourceType: resourceTypes,
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

  return (
    <div className={classes.container}>
      {resourceTypes.map((resourceType) => {
        return (
          <Chart
            displayLegend={displayLegend}
            displayType={displayType}
            displayValues={displayValues}
            key={resourceType}
            refreshCount={refreshCount}
            refreshIntervalToUse={refreshIntervalToUse}
            resourceType={resourceType}
            resourceTypes={resourceTypes}
            resources={resources}
            title={
              includes('host', resourceType) ? t(labelHosts) : t(labelServices)
            }
            unit={unit}
          />
        );
      })}
    </div>
  );
};

export default StatusChart;
