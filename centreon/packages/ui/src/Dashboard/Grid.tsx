import { ReactElement, useMemo } from 'react';

import { scaleLinear } from '@visx/scale';
import { Grid as VisxGrid } from '@visx/visx';

import { useTheme } from '@mui/material';

import { useMemoComponent } from '../utils';

import { maxColumns, rowHeight } from './utils';

interface Props {
  columns: number;
  height: number;
  width: number;
}

const Grid = ({ width, height, columns }: Props): ReactElement => {
  const theme = useTheme();

  const xScale = useMemo(
    () =>
      scaleLinear({
        domain: [0, maxColumns],
        range: [0, width]
      }),
    [width]
  );

  const numberOfRows = Math.floor(height / (rowHeight + 16));

  const yScale = useMemo(
    () =>
      scaleLinear({
        domain: [0, numberOfRows],
        range: [0, height]
      }),
    [height]
  );

  const tick = maxColumns / columns;

  const xTickValues = Array(columns)
    .fill(0)
    .map((_, index) => index * tick);

  return useMemoComponent({
    Component: (
      <svg style={{ height, position: 'absolute', width }}>
        <VisxGrid.GridColumns
          height={height}
          scale={xScale}
          stroke={theme.palette.divider}
          tickValues={xTickValues}
          width={width}
        />
        <VisxGrid.GridRows
          height={height}
          scale={yScale}
          stroke={theme.palette.divider}
          top={-10}
          width={width}
        />
      </svg>
    ),
    memoProps: [height, width, columns]
  });
};

export default Grid;
