import { equals } from 'ramda';

import { PieChart, BarStack } from '@centreon/ui';

import { DisplayType, PanelOptions } from '../../models';

import { useStyles } from './Chart.styles';

const Chart = ({
  displayType,
  states,
  displayLegend,
  displayValues,
  resourceType,
  unit,
  data,
  displayPredominentInformation,
  title
}: Omit<PanelOptions, 'refreshInterval' | 'refreshIntervalCustom'> & {
  data?;
  title?;
}): JSX.Element => {
  const { classes } = useStyles({
    displaySingleChart: equals(resourceType.length, 1)
  });

  return (
    <div className={classes.container}>
      {equals(displayType, DisplayType.Pie) ||
      equals(displayType, DisplayType.Donut) ? (
        <div className={classes.pieChart}>
          <PieChart
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            title={title}
            unit={unit}
            variant={displayType}
          />
        </div>
      ) : (
        <div className={classes.barStack}>
          <BarStack
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            title={title}
            unit={unit}
            variant={displayType}
          />
        </div>
      )}
    </div>
  );
};

export default Chart;
