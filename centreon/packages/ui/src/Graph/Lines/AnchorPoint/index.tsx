import { Shape } from '@visx/visx';

import { grey } from '@mui/material/colors';

interface Props {
  areaColor: string;
  graphHeight: number;
  graphWidth: number;
  lineColor: string;
  positionX: number;
  positionY: number;
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
  const { positionX, positionY, graphHeight, graphWidth } = rest;

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

      <Shape.Line
        from={{ x: positionX, y: 0 }}
        pointerEvents="none"
        stroke={grey[400]}
        strokeWidth={1}
        to={{ x: positionX, y: graphHeight }}
      />
      <Shape.Line
        from={{ x: 0, y: positionY }}
        pointerEvents="none"
        stroke={grey[400]}
        strokeWidth={1}
        to={{ x: graphWidth, y: positionY }}
      />
    </g>
  );
};

export default Point;
