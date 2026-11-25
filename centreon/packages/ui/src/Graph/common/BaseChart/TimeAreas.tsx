import { useTheme } from '@mui/material';

import { PatternLines } from '@visx/pattern';
import type { ScaleTime } from 'd3-scale';
import { clamp } from 'ramda';
import { type ReactElement, useId } from 'react';

import type { TimeArea } from '../models';

interface Props {
  areas: Array<TimeArea>;
  isHorizontal?: boolean;
  size: number;
  timeScale: ScaleTime<number, number>;
}

const TimeAreas = ({
  areas,
  isHorizontal = true,
  size,
  timeScale
}: Props): ReactElement => {
  const theme = useTheme();
  const id = useId();
  const [rangeStart, rangeEnd] = timeScale.range();

  const toPosition = (date: Date | string): number =>
    clamp(rangeStart, rangeEnd, timeScale(new Date(date)));

  const displayedAreas = areas.filter(
    ({ start, end }) => toPosition(end) - toPosition(start) > 0
  );

  return (
    <g>
      {displayedAreas.map(
        ({ start, end, backgroundColor, pattern, label }, index) => {
          const from = toPosition(start);
          const length = toPosition(end) - from;

          // useId contains characters that break url(#…) references.
          const patternId = `time-area-${id.replace(/[^\w-]/g, '')}-${index}`;
          const rect = isHorizontal
            ? { height: size, width: length, x: from, y: 0 }
            : { height: length, width: size, x: 0, y: from };

          return (
            <g data-testid="time-area" key={patternId}>
              {backgroundColor && <rect {...rect} fill={backgroundColor} />}
              {pattern && (
                <>
                  <PatternLines {...pattern} id={patternId} />
                  <rect {...rect} fill={`url(#${patternId})`} />
                </>
              )}
              {label && (
                <text
                  fill={theme.palette.text.secondary}
                  paintOrder="stroke"
                  stroke={theme.palette.background.paper}
                  strokeWidth={3}
                  style={{ fontSize: '10px' }}
                  x={rect.x + 4}
                  y={rect.y + 12}
                >
                  {label}
                </text>
              )}
            </g>
          );
        }
      )}
    </g>
  );
};

export default TimeAreas;
