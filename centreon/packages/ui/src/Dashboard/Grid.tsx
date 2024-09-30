import { MutableRefObject, ReactElement, useMemo } from 'react';

import { scaleLinear } from '@visx/scale';
import { Grid as VisxGrid } from '@visx/visx';

import { useTheme } from '@mui/material';

import { useMemoComponent } from '../utils';

import { maxColumns, rowHeight } from './utils';

interface Props {
  columns: number;
  height: number;
  width: number;
  containerRef: MutableRefObject<HTMLDivElement | null>;
}

const Grid = ({
  width,
  height,
  columns,
  containerRef
}: Props): ReactElement => {
  const theme = useTheme();

  const containerHeight = useMemo(
    () => containerRef.current?.clientHeight || height,
    [containerRef.current, height]
  );

  const xScale = useMemo(
    () =>
      scaleLinear({
        domain: [0, maxColumns],
        range: [0, width]
      }),
    [width]
  );

  const numberOfRows = Math.floor(containerHeight / (rowHeight + 16));

  const yScale = useMemo(
    () =>
      scaleLinear({
        domain: [0, numberOfRows],
        range: [0, containerHeight]
      }),
    [containerHeight]
  );

  const tick = maxColumns / columns;

  const xTickValues = Array(columns)
    .fill(0)
    .map((_, index) => index * tick);

  const yTickValues = Array(numberOfRows)
    .fill(0)
    .map((_, index) => index);

  return useMemoComponent({
    Component: (
      <svg style={{ height: '100%', position: 'absolute', width }}>
        <VisxGrid.Grid
          columnTickValues={xTickValues}
          rowTickValues={yTickValues}
          height={height}
          yScale={yScale}
          xScale={xScale}
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
