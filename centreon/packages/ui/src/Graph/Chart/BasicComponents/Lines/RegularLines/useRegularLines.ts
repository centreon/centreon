import { difference } from 'ramda';

import { getSortedStackedLines } from '../../../../common/timeSeries';
import { Line } from '../../../../common/timeSeries/models';

interface RegularLines {
  regularLines: Array<Line>;
}
const useRegularLines = ({ lines }): RegularLines => {
  const stackedLines = getSortedStackedLines(lines);

  return { regularLines: difference(lines, stackedLines) };
};

export default useRegularLines;
