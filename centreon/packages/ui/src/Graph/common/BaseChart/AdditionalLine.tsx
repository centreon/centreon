import { useMemo } from 'react';
import { AdditionalLineProps } from '../models';

interface Props extends AdditionalLineProps {
  graphWidth: number;
  yScale;
}

const AdditionalLine = ({
  yValue,
  color,
  text,
  graphWidth,
  yScale
}: Props): JSX.Element => {
  const positionY = useMemo(() => yScale(yValue), [yValue, yScale]);

  return (
    <g>
      {text && (
        <text x={8} y={positionY - 8} fill={color} style={{ fontSize: '10px' }}>
          {text}
        </text>
      )}
      <line
        x1={0}
        x2={graphWidth}
        y1={positionY}
        y2={positionY}
        stroke={color}
        data-testid={`${color}-${yValue}`}
      />
    </g>
  );
};

export default AdditionalLine;
