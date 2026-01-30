import { Box } from '@mui/material';

import { useSetAtom } from 'jotai';
import { ReactElement, useCallback, useEffect, useState } from 'react';
import {
  type Layout,
  LayoutItem,
  ReactGridLayout,
  useContainerWidth
} from 'react-grid-layout';

import { useMemoComponent } from '..';
import { isResizingItemAtom } from './atoms';
import { useDashboardLayoutStyles } from './Dashboard.styles';
import { getColumnsFromScreenSize, getLayout, rowHeight } from './utils';
import 'react-grid-layout/css/styles.css';

interface DashboardLayoutProps<T> {
  additionalMemoProps?: Array<unknown>;
  changeLayout?: (newLayout: Layout) => void;
  children: Array<ReactElement>;
  displayGrid?: boolean;
  isStatic?: boolean;
  layout: Array<T>;
}

const Handle = (axis: string, ref: React.Ref<HTMLSpanElement>) => {
  return (
    <span
      className={`react-resizable-handle react-resizable-handle-${axis}`}
      ref={ref}
    >
      <span className={`handle-content-${axis}`} />
    </span>
  );
};

const DashboardLayout = <T extends LayoutItem>({
  children,
  changeLayout,
  layout,
  isStatic = false,
  additionalMemoProps = []
}: DashboardLayoutProps<T>): ReactElement => {
  const { width, containerRef } = useContainerWidth();

  const { classes } = useDashboardLayoutStyles(isStatic);

  const [columns, setColumns] = useState(getColumnsFromScreenSize());

  const setIsResizingItem = useSetAtom(isResizingItemAtom);

  const resize = (): void => {
    setColumns(getColumnsFromScreenSize());
  };

  const startResize = useCallback(
    (_: Layout, _e: LayoutItem | null, newItem: LayoutItem | null) => {
      setIsResizingItem((newItem as LayoutItem & { id: string })?.id ?? null);
    },
    [setIsResizingItem]
  );

  const stopResize = useCallback(() => {
    setIsResizingItem(null);
  }, [setIsResizingItem]);

  useEffect(() => {
    window.addEventListener('resize', resize);

    return (): void => {
      window.removeEventListener('resize', resize);
    };
  }, [resize]);

  return useMemoComponent({
    Component: (
      <Box ref={containerRef} sx={{ overflowX: 'hidden', overflowY: 'auto' }}>
        <Box className={classes.container}>
          <ReactGridLayout
            gridConfig={{ cols: columns, margin: [12, 12], rowHeight }}
            layout={getLayout(layout)}
            onLayoutChange={changeLayout}
            onResizeStart={startResize}
            onResizeStop={stopResize}
            resizeConfig={{
              handleComponent: Handle,
              handles: ['s', 'e', 'se', 'sw', 'w']
            }}
            width={width}
          >
            {children}
          </ReactGridLayout>
        </Box>
      </Box>
    ),
    memoProps: [columns, layout, isStatic, ...additionalMemoProps]
  });
};

export default DashboardLayout;
