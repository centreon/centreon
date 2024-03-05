import { equals, isNil } from 'ramda';

import { PieChart, BarStack } from '@centreon/ui';

import { ChartType, DisplayType } from '../models';
import useLoadResources from '../useLoadResources';
import { goToUrl } from '../../../utils';
import Legend from '../Legend/Legend';
import TooltipContent from '../Tooltip/Tooltip';

import { useStyles } from './Chart.styles';
import ChartSkeleton from './LoadingSkeleton';

const Chart = ({
  displayType,
  displayLegend,
  displayValues,
  resourceType,
  unit,
  title,
  resourceTypes,
  refreshCount,
  refreshIntervalToUse,
  resources,
  labelNoDataFound,
  getLinkToResourceStatusPage
}: ChartType): JSX.Element => {
  const isSingleChart = equals(resourceTypes.length, 1);

  const isPieCharts =
    equals(displayType, DisplayType.Pie) ||
    equals(displayType, DisplayType.Donut);

  const { classes } = useStyles({ displayType, isSingleChart });

  const { data, isLoading } = useLoadResources({
    refreshCount,
    refreshIntervalToUse,
    resourceType,
    resources
  });

  const goToResourceStatusPage = (status): void => {
    const url = getLinkToResourceStatusPage(status, resourceType);

    goToUrl(url)();
  };

  if (isLoading && isNil(data)) {
    return <ChartSkeleton />;
  }

  if (isNil(data)) {
    return <div />;
  }

  return (
    <div className={classes.container}>
      {isPieCharts ? (
        <div className={classes.pieChart}>
          <PieChart
            Legend={Legend((status) =>
              getLinkToResourceStatusPage(status, resourceType)
            )}
            TooltipContent={TooltipContent}
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            labelNoDataFound={labelNoDataFound}
            title={title}
            unit={unit}
            variant={displayType as 'pie' | 'donut'}
            onArcClick={({ label: status }) => {
              goToResourceStatusPage(status);
            }}
          />
        </div>
      ) : (
        <div className={classes.barStack}>
          <BarStack
            Legend={Legend((status) =>
              getLinkToResourceStatusPage(status, resourceType)
            )}
            TooltipContent={TooltipContent}
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            labelNoDataFound={labelNoDataFound}
            legendDirection={
              equals(displayType, DisplayType.Horizontal) ? 'row' : 'column'
            }
            size={80}
            title={title}
            unit={unit}
            variant={displayType as 'horizontal' | 'vertical'}
            onSingleBarClick={({ key: status }) => {
              goToResourceStatusPage(status);
            }}
          />
        </div>
      )}
    </div>
  );
};

export default Chart;
