import { equals, isNil } from 'ramda';

import { PieChart, BarStack } from '@centreon/ui';

import { ChartType, DisplayType } from '../../models';
import { Legend, TooltipContent, ChartSkeleton } from '..';
import useLoadResources from '../../useLoadResources';
import { goToUrl } from '../../../../utils';

import { useStyles } from './Chart.styles';
import { useChart } from './useChart';

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
  const { classes } = useStyles();

  const { data, isLoading } = useLoadResources({
    refreshCount,
    refreshIntervalToUse,
    resourceType,
    resources
  });

  const { barStackDimensions, isPieCharts, pieChartDimensions } = useChart({
    displayType,
    resourceTypes
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
        <div className={classes.pieChart} style={pieChartDimensions}>
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
        <div style={barStackDimensions}>
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
