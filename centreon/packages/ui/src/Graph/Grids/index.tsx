import { ScaleLinear } from 'd3-scale';
import { Grid } from '@visx/visx';

import { GridsModel } from '../models';

interface Props {
  // [x: string]: unknown;
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
  ...rest
}: Props): JSX.Element => {
  return (
    <>
      <Grid.GridRows
        height={height}
        scale={leftScale}
        width={width}
        {...rest?.row}
      />
      <Grid.GridColumns
        height={height}
        scale={xScale}
        width={width}
        {...rest?.column}
      />
    </>
  );
};

export default Grids;
