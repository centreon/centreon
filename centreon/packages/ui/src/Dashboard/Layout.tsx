import { useEffect, useState } from 'react';

import { Responsive } from '@visx/visx';
import GridLayout, { WidthProvider, Layout } from 'react-grid-layout';
import 'react-grid-layout/css/styles.css';

import { Responsive as ResponsiveHeight, useMemoComponent } from '..';

import { useDashboardLayoutStyles } from './Dashboard.styles';
import { getColumnsFromScreenSize, getLayout, rowHeight } from './utils';
import Grid from './Grid';

const ReactGridLayout = WidthProvider(GridLayout);

interface DashboardLayoutProps<T> {
  changeLayout?: (newLayout: Array<Layout>) => void;
  children: Array<JSX.Element>;
  displayGrid?: boolean;
  layout: Array<T>;
}

const Layout = <T extends Layout>({
  children,
  changeLayout,
  displayGrid,
  layout
}: DashboardLayoutProps<T>): JSX.Element => {
  const { classes } = useDashboardLayoutStyles();

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
      <ResponsiveHeight>
        <Responsive.ParentSize>
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
        </Responsive.ParentSize>
      </ResponsiveHeight>
    ),
    memoProps: [columns, layout, displayGrid]
  });
};

export default Layout;
