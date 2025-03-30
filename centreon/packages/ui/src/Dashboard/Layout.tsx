import { useCallback, useEffect, useRef, useState } from 'react';

import { useSetAtom } from 'jotai';
import GridLayout, { Layout, WidthProvider } from 'react-grid-layout';

import { ParentSize, useMemoComponent } from '..';

import { Box } from '@mui/material';
import { useDashboardLayoutStyles } from './Dashboard.styles';
import { isResizingItemAtom } from './atoms';
import { getColumnsFromScreenSize, getLayout, rowHeight } from './utils';

const ReactGridLayout = WidthProvider(GridLayout);

interface DashboardLayoutProps<T> {
  additionalMemoProps?: Array<unknown>;
  changeLayout?: (newLayout: Array<Layout>) => void;
  children: Array<JSX.Element>;
  displayGrid?: boolean;
  isStatic?: boolean;
  layout: Array<T>;
}

const Handle = (axis, ref) => {
  return (
    <span
      className={`react-resizable-handle react-resizable-handle-${axis}`}
      ref={ref}
    >
      <span className={`handle-content-${axis}`} />
    </span>
  );
};

const DashboardLayout = <T extends Layout>({
  children,
  changeLayout,
  layout,
  isStatic = false,
  additionalMemoProps = []
}: DashboardLayoutProps<T>): JSX.Element => {
  const dashboardContainerRef = useRef<HTMLDivElement | null>(null);

  const { classes } = useDashboardLayoutStyles(isStatic);

  const [columns, setColumns] = useState(getColumnsFromScreenSize());

  const setIsResizingItem = useSetAtom(isResizingItemAtom);

  const resize = (): void => {
    setColumns(getColumnsFromScreenSize());
  };

  const startResize = useCallback((_, _e, newItem: T) => {
    setIsResizingItem(newItem.i);
  }, []);

  const stopResize = useCallback(() => {
    setIsResizingItem(null);
  }, []);

  useEffect(() => {
    window.addEventListener('resize', resize);

    return (): void => {
      window.removeEventListener('resize', resize);
    };
  }, []);

  return useMemoComponent({
    Component: (
      <Box
        ref={dashboardContainerRef}
        sx={{ overflowY: 'auto', overflowX: 'hidden' }}
      >
        <ParentSize>
          {({ width }): JSX.Element => (
            <Box className={classes.container}>
              <ReactGridLayout
                cols={columns}
                layout={getLayout(layout)}
                margin={[12, 12]}
                resizeHandles={['s', 'e', 'se', 'sw', 'w']}
                rowHeight={rowHeight}
                width={width}
                onLayoutChange={changeLayout}
                onResizeStart={startResize}
                onResizeStop={stopResize}
                resizeHandle={Handle}
              >
                {children}
              </ReactGridLayout>
            </Box>
          )}
        </ParentSize>
      </Box>
    ),
    memoProps: [columns, layout, isStatic, ...additionalMemoProps]
  });
};

export default DashboardLayout;
