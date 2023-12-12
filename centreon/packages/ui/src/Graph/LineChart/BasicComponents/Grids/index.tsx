import { Grid } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';

interface Props {
  height: number;
  leftScale: ScaleLinear<number, number>;
  width: number;
  xScale: ScaleLinear<number, number>;
}

const Grids = ({ height, width, leftScale, xScale }: Props): JSX.Element => {
  return (
    <g>
      <Grid.GridRows height={height} scale={leftScale} width={width} />
      <Grid.GridColumns height={height} scale={xScale} width={width} />
    </g>
  );
};

export default Grids;
