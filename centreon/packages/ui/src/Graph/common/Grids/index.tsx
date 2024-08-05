import { useMemo } from 'react';

import { Grid } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';
import { includes } from 'ramda';

import { ChartAxis } from '../../Chart/models';

interface Props extends Pick<ChartAxis, 'gridLinesType'> {
  height: number;
  leftScale: ScaleLinear<number, number>;
  width: number;
  xScale: ScaleLinear<number, number>;
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
      {displayRows && (
        <Grid.GridRows height={height} scale={leftScale} width={width} />
      )}
      {displayColumns && (
        <Grid.GridColumns height={height} scale={xScale} width={width} />
      )}
    </g>
  );
};

export default Grids;
