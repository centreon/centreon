import { Grid } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';

import { GridsModel } from '../models';

interface Props {
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
}: Props & GridsModel): JSX.Element => {
  return (
    <g>
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
    </g>
  );
};

export default Grids;
