import { useEffect, useState } from 'react';

import GridLayout, { Layout, WidthProvider } from 'react-grid-layout';
import 'react-grid-layout/css/styles.css';

import {
  Responsive as ResponsiveHeight,
  ParentSize,
  useMemoComponent
} from '..';

import { useDashboardLayoutStyles } from './Dashboard.styles';
import { getColumnsFromScreenSize, getLayout, rowHeight } from './utils';
import Grid from './Grid';

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
  const { classes } = useDashboardLayoutStyles(isStatic);

  const [columns, setColumns] = useState(getColumnsFromScreenSize());

  const resize = (): void => {
    setColumns(getColumnsFromScreenSize());
  };

  useEffect(() => {
    window.addEventListener('resize', resize);

    return (): void => {
      window.removeEventListener('resize', resize);
    };
  }, []);

  return useMemoComponent({
    Component: (
      <ResponsiveHeight margin={40}>
        <ParentSize>
          {({ width, height }): JSX.Element => (
            <div className={classes.container}>
              {displayGrid && (
                <Grid columns={columns} height={height} width={width} />
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
              >
                {children}
              </ReactGridLayout>
            </div>
          )}
        </ParentSize>
      </ResponsiveHeight>
    ),
    memoProps: [columns, layout, displayGrid, isStatic, ...additionalMemoProps]
  });
};

export default DashboardLayout;
