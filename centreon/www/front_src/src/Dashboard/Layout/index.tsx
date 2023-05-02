import { FC, useEffect } from 'react';
import 'react-grid-layout/css/styles.css';

import { Responsive } from '@visx/visx';
import GridLayout, { WidthProvider, Layout } from 'react-grid-layout';
import { useAtom, useAtomValue } from 'jotai';
import { equals, map, propEq } from 'ramda';

import { Responsive as ResponsiveHeight } from '@centreon/ui';

import { columnsAtom, dashboardAtom, isEditingAtom } from '../atoms';
import { Widget } from '../models';

import DashboardWidget from './Widget';
import EditionGrid from './EditionGrid';
import useDashboardStyles from './useDashboardStyles';

const ReactGridLayout = WidthProvider(GridLayout);

const Layout: FC = () => {
  const { classes } = useDashboardStyles();

  const [dashboard, setDashboard] = useAtom(dashboardAtom);
  const [columns, setColumns] = useAtom(columnsAtom);
  const isEditing = useAtomValue(isEditingAtom);

  const changeLayout = (layout: Array<Layout>): void => {
    const isOneColumnDisplay = equals(columns, 1);
    if (isOneColumnDisplay) {
      return;
    }

    const newLayout = map<Layout, Widget>((widget) => {
      const currentWidget = dashboard.layout.find(propEq('i', widget.i));

      return {
        ...widget,
        options: currentWidget?.options,
        widgetConfiguration: currentWidget?.widgetConfiguration
      };
    }, layout);

    setDashboard({
      layout: newLayout
    });
  };

  const getLayout = (): Array<Widget> => {
    if (equals(columns, 25)) {
      return dashboard.layout;
    }

    return dashboard.layout
      .sort((a, b) => a.x + a.y - (b.x + b.y))
      .map((widget) => ({
        ...widget,
        w: 1
      }));
  };

  const resize = (): void => {
    setColumns(window.innerWidth > 768 ? 25 : 1);
  };

  useEffect(() => {
    resize();

    window.addEventListener('resize', resize);

    return () => {
      window.removeEventListener('resize', resize);
    };
  }, []);

  return (
    <ResponsiveHeight>
      <Responsive.ParentSize>
        {({ width, height }): JSX.Element => (
          <div className={classes.container}>
            {isEditing && <EditionGrid height={height} width={width} />}
            <ReactGridLayout
              cols={columns}
              containerPadding={[4, 0]}
              layout={getLayout()}
              resizeHandles={['s', 'e', 'se']}
              rowHeight={30}
              width={width}
              onLayoutChange={changeLayout}
            >
              {dashboard.layout.map(({ i }) => {
                return (
                  <div key={i}>
                    <DashboardWidget id={i} key={i} />
                  </div>
                );
              })}
            </ReactGridLayout>
          </div>
        )}
      </Responsive.ParentSize>
    </ResponsiveHeight>
  );
};

export default Layout;
