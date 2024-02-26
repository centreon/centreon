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
  resourceTypes
}: Pick<ChartType, 'displayType' | 'resourceTypes'>): UseChartState => {
  const isSingleChart = equals(resourceTypes.length, 1);

  const isPieCharts =
    equals(displayType, DisplayType.Pie) ||
    equals(displayType, DisplayType.Donut);

  const barStackDimensions = {
    height:
      isSingleChart && equals(displayType, DisplayType.Horizontal)
        ? '48%'
        : '96%',
    width:
      isSingleChart && equals(displayType, DisplayType.Vertical) ? '48%' : '96%'
  };

  const pieChartDimensions = {
    height: '100%',
    width: isSingleChart ? '48%' : '96%'
  };

  return { barStackDimensions, isPieCharts, pieChartDimensions };
};
