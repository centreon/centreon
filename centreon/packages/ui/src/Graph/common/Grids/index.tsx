import { Grid } from '@visx/visx';
import type { ScaleBand, ScaleLinear, ScaleTime } from 'd3-scale';
import { includes } from 'ramda';
import { useMemo } from 'react';

import type { ChartAxis } from '../../Chart/models';

interface Props extends Pick<ChartAxis, 'gridLinesType'> {
  height: number;
  leftScale?:
    | ScaleLinear<number, number>
    | ScaleTime<number, number>
    | ScaleBand<number>;
  width: number;
  xScale?:
    | ScaleLinear<number, number>
    | ScaleTime<number, number>
    | ScaleBand<number>;
}

const Grids = ({
  height,
  width,
  leftScale,
  xScale,
  gridLinesType
}: Props): JSX.Element => {
  const displayRows = useMemo(
    () => includes(gridLinesType, ['all', 'horizontal', undefined]),
    [gridLinesType]
  );
  const displayColumns = useMemo(
    () => includes(gridLinesType, ['all', 'vertical', undefined]),
    [gridLinesType]
  );

  return (
    <g>
      {displayRows && leftScale && (
        <Grid.GridRows height={height} scale={leftScale} width={width} />
      )}
      {displayColumns && xScale && (
        <Grid.GridColumns height={height} scale={xScale} width={width} />
      )}
    </g>
  );
};

export default Grids;
