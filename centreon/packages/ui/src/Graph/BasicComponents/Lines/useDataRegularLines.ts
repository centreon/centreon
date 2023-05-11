import { ScaleLinear } from 'd3-scale';

import { Line, TimeValue } from '../../timeSeries/models';
import { displayArea } from '../../helpers/index';

import { AreaRegularLines } from './models';

interface RegularLinesData {
  display: boolean;
  leftScale: ScaleLinear<number, number>;
  lines: Array<Line>;
  rightScale: ScaleLinear<number, number>;
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}

const useDataRegularLines = (
  areaRegularLines: AreaRegularLines
): RegularLinesData => {
  const { lines, timeSeries, display: displayAreaRegular } = areaRegularLines;

  const display = displayAreaRegular && displayArea(lines);

  const leftScale = areaRegularLines?.leftScale;
  const rightScale = areaRegularLines?.rightScale;
  const xScale = areaRegularLines?.xScale;

  return {
    display,
    leftScale,
    lines,
    rightScale,
    timeSeries,
    xScale
  };
};

export default useDataRegularLines;
