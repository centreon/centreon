import { alpha } from '@mui/system';

interface Props {
  lineColor: string;
  x: number;
  y: number;
}

const Point = ({ lineColor, x, y }: Props): JSX.Element => (
  <circle
    cx={x}
    cy={y}
    fill={alpha(lineColor, 0.5)}
    r={4}
    stroke={lineColor}
    strokeWidth={2}
  />
);

export default Point;
