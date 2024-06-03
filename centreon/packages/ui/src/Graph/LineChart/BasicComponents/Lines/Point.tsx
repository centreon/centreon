import { TimeValue } from '../../../common/timeSeries/models';

interface Props {
  lineColor: string;
  metric_id: number;
  timeSeries: Array<TimeValue>;
  timeTick: Date;
  xScale;
  yPoint: number;
  yScale;
}

const Point = ({
  metric_id,
  timeSeries,
  timeTick,
  yScale,
  yPoint,
  lineColor,
  xScale
}: Props): JSX.Element | null => {
  const x = xScale(timeTick);

  if (!x || !yPoint) {
    return null;
  }

  return (
    <circle
      cx={x}
      cy={yPoint}
      fill={lineColor}
      r={2}
      stroke={lineColor}
      strokeWidth={2}
    />
  );
};

export default Point;
