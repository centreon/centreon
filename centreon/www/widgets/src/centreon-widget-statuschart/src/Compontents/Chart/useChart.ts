import { equals } from 'ramda';

import { ChartType, DisplayType } from '../../models';

interface Dimension {
  height: string;
  width: string;
}

interface UseChartState {
  barStackDimensions: Dimension;
  isPieCharts: boolean;
  pieChartDimensions: Dimension;
}

export const useChart = ({
  displayType,
  resourceType
}: Pick<ChartType, 'displayType' | 'resourceType'>): UseChartState => {
  const isPieCharts =
    equals(displayType, DisplayType.Pie) ||
    equals(displayType, DisplayType.Donut);

  const barStackDimensions = {
    height:
      equals(resourceType.length, 1) &&
      equals(displayType, DisplayType.Horizontal)
        ? '48%'
        : '96%',
    width:
      equals(resourceType.length, 1) &&
      equals(displayType, DisplayType.Vertical)
        ? '48%'
        : '96%'
  };

  const pieChartDimensions = {
    height: '100%',
    width: equals(resourceType.length, 1) ? '48%' : '96%'
  };

  return { barStackDimensions, isPieCharts, pieChartDimensions };
};
