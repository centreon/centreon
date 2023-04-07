import { Grid } from '@visx/visx';

interface Props {
  height: number;
  leftScale: any;
  width: number;
  xScale;
}

const Grids = ({ height, width, leftScale, xScale }: Props): JSX.Element => {
  return (
    <>
      <Grid.GridRows height={height} scale={leftScale} width={width} />
      <Grid.GridColumns height={height} scale={xScale} width={width} />
    </>
  );
};

export default Grids;
