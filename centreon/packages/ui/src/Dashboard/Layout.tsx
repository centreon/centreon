import { useCallback, useEffect, useRef, useState } from 'react';

import { useSetAtom } from 'jotai';
import GridLayout, { Layout, WidthProvider } from 'react-grid-layout';
import 'react-grid-layout/css/styles.css';

import {
  ParentSize,
  Responsive as ResponsiveHeight,
  useMemoComponent
} from '..';

import { useDashboardLayoutStyles } from './Dashboard.styles';
import Grid from './Grid';
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

const DashboardLayout = <T extends Layout>({
  children,
  changeLayout,
  displayGrid,
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
      <ResponsiveHeight margin={40}>
        <div ref={dashboardContainerRef}>
          <ParentSize>
            {({ width, height }): JSX.Element => (
              <div className={classes.container}>
                {displayGrid && (
                  <Grid
                    columns={columns}
                    height={height}
                    width={width}
                    containerRef={dashboardContainerRef}
                  />
                )}
                <ReactGridLayout
                  cols={columns}
                  containerPadding={[4, 0]}
                  layout={getLayout(layout)}
                  margin={[20, 20]}
                  resizeHandles={['s', 'e', 'se']}
                  rowHeight={rowHeight}
                  width={width}
                  onLayoutChange={changeLayout}
                  onResizeStart={startResize}
                  onResizeStop={stopResize}
                >
                  {children}
                </ReactGridLayout>
              </div>
            )}
          </ParentSize>
        </div>
      </ResponsiveHeight>
    ),
    memoProps: [columns, layout, displayGrid, isStatic, ...additionalMemoProps]
  });
};

export default DashboardLayout;
