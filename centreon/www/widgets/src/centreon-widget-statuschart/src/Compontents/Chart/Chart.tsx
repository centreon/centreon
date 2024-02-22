import { equals } from 'ramda';

import { PieChart, BarStack } from '@centreon/ui';

import { DisplayType, ChartType } from '../../models';
import { Legend, TooltipContent, ChartSkeleton } from '..';
import useLoadResources from '../../useLoadResources';
import { useLegendStyles } from '../Legend/Legend.styles';

import { useStyles } from './Chart.styles';
import { useChart } from './useChart';

const Chart = ({
  displayType,
  displayLegend,
  displayValues,
  resourceType,
  unit,
  title,
  type,
  refreshCount,
  refreshIntervalToUse,
  resources
}: ChartType): JSX.Element => {
  const { classes } = useStyles();
  const { classes: legendClasses } = useLegendStyles({
    direction: equals(displayType, DisplayType.Horizontal) ? 'row' : 'column'
  });

  const { data, isLoading } = useLoadResources({
    refreshCount,
    refreshIntervalToUse,
    resources,
    type
  });

  const { barStackDimensions, isPieCharts, pieChartDimensions } = useChart({
    displayType,
    resourceType
  });

  if (isLoading) {
    return <ChartSkeleton />;
  }

  return (
    <div className={classes.container}>
      {isPieCharts ? (
        <div className={classes.pieChart} style={pieChartDimensions}>
          <PieChart
            Legend={Legend(legendClasses)}
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            title={title}
            tooltipContent={TooltipContent}
            unit={unit}
            variant={displayType}
          />
        </div>
      ) : (
        <div style={barStackDimensions}>
          <BarStack
            Legend={Legend(legendClasses)}
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            size={80}
            title={title}
            tooltipContent={TooltipContent}
            unit={unit}
            variant={displayType}
          />
        </div>
      )}
    </div>
  );
};

export default Chart;
