interface Props {
  lineColor: string;
  x: number;
  y: number;
}

const Point = ({ lineColor, x, y }: Props): JSX.Element => {
  return (
    <circle
      cx={x}
      cy={y}
      fill="white"
      r={4}
      stroke={lineColor}
      strokeWidth={2}
    />
  );
};

export default Point;
