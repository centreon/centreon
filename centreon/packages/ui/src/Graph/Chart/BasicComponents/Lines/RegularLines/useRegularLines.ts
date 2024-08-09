import { difference } from 'ramda';

import { Line } from '../../../../common/timeSeries/models';
import { getSortedStackedLines } from '../../../../common/timeSeries';

interface RegularLines {
  regularLines: Array<Line>;
}
const useRegularLines = ({ lines }): RegularLines => {
  const stackedLines = getSortedStackedLines(lines);

  return { regularLines: difference(lines, stackedLines) };
};

export default useRegularLines;
