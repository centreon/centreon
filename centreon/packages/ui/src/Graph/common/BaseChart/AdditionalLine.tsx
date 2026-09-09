import type { ScaleLinear } from 'd3-scale';
import { ReactElement, useMemo } from 'react';

import type { AdditionalLineProps } from '../models';

interface Props extends AdditionalLineProps {
  graphWidth: number;
  yScale?: ScaleLinear<number, number>;
}

const AdditionalLine = ({
  yValue,
  color,
  text,
  graphWidth,
  yScale
}: Props): ReactElement | null => {
  const positionY = useMemo(() => yScale?.(yValue), [yValue, yScale]);

  if (positionY === undefined) {
    return null;
  }

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
