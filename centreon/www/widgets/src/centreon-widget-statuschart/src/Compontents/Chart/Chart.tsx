import { equals, isNil } from 'ramda';

import { PieChart, BarStack } from '@centreon/ui';

import { ChartType, DisplayType } from '../../models';
import { Legend, TooltipContent, ChartSkeleton } from '..';
import useLoadResources from '../../useLoadResources';

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
  labelNoDataFound
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
            Legend={Legend}
            TooltipContent={TooltipContent}
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            labelNoDataFound={labelNoDataFound}
            title={title}
            unit={unit}
            variant={displayType as 'pie' | 'donut'}
          />
        </div>
      ) : (
        <div style={barStackDimensions}>
          <BarStack
            Legend={Legend}
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
          />
        </div>
      )}
    </div>
  );
};

export default Chart;
