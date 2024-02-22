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
  refreshCount,
  isFromPreview
}: StatusChartProps): JSX.Element => {
  const {
    displayType,
    refreshInterval,
    refreshIntervalCustom,
    displayLegend,
    displayValues,
    resourceType,
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
      {includes('host', resourceType) && (
        <Chart
          displayLegend={displayLegend}
          displayType={displayType}
          displayValues={displayValues}
          refreshCount={refreshCount}
          refreshIntervalToUse={refreshIntervalToUse}
          resourceType={resourceType}
          resources={resources}
          title={t(labelHosts)}
          type="host"
          unit={unit}
        />
      )}
      {includes('service', resourceType) && (
        <Chart
          displayLegend={displayLegend}
          displayType={displayType}
          displayValues={displayValues}
          refreshCount={refreshCount}
          refreshIntervalToUse={refreshIntervalToUse}
          resourceType={resourceType}
          resources={resources}
          title={t(labelServices)}
          type="service"
          unit={unit}
        />
      )}
    </div>
  );
};

export default StatusChart;
