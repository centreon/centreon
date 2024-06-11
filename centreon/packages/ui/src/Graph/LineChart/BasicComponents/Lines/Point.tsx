import { isNil } from 'ramda';

interface Props {
  lineColor: string;
  radius: number;
  timeTick: Date;
  xScale;
  yPoint: number;
}

const Point = ({
  timeTick,
  yPoint,
  lineColor,
  xScale,
  radius
}: Props): JSX.Element | null => {
  const x = xScale(timeTick);

  if (isNil(x) || isNil(yPoint)) {
    return null;
  }

  return (
    <circle
      cx={x}
      cy={yPoint}
      fill={lineColor}
      r={radius}
      stroke={lineColor}
      strokeWidth={2}
    />
  );
};

export default Point;
