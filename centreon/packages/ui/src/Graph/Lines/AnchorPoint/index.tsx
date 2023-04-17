interface Props {
  areaColor: string;
  lineColor: string;
  transparency: number;
  x: number;
  y: number;
}

const Point = ({
  areaColor,
  lineColor,
  transparency,
  x,
  y,
  ...rest
}: Props): JSX.Element => {
  return (
    <g>
      <circle
        cx={x}
        cy={y}
        fill={areaColor}
        fillOpacity={(1 - transparency * 0.01).toString()}
        r={3}
        stroke={lineColor}
        {...rest}
      />
    </g>
  );
};

export default Point;
