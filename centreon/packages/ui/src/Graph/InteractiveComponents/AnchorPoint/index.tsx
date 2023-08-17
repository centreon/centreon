import { alpha } from '@mui/system';

interface Props {
  lineColor: string;
  x: number;
  y: number;
}

const Point = ({ lineColor, x, y }: Props): JSX.Element => (
  <circle
    fill={alpha(lineColor, 0.5)}
    r={4}
    stroke={lineColor}
    strokeWidth={2}
    style={{
      transform: `translate(${x}px, ${y}px)`,
      transition: 'transform 0.08s ease-out'
    }}
  />
);

export default Point;
