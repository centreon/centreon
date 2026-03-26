import { useMemo } from 'react';

import type { AdditionalLineProps } from '../models';

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
        <text fill={color} style={{ fontSize: '10px' }} x={8} y={positionY - 8}>
          {text}
        </text>
      )}
      <line
        data-testid={`${color}-${yValue}`}
        stroke={color}
        x1={0}
        x2={graphWidth}
        y1={positionY}
        y2={positionY}
      />
    </g>
  );
};

export default AdditionalLine;
