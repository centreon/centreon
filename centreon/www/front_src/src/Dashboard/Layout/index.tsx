import { FC } from 'react';
import 'react-grid-layout/css/styles.css';

import { Responsive } from '@visx/visx';
import {
  WidthProvider,
  Responsive as ResponsiveGridLayout,
  Layout
} from 'react-grid-layout';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { equals, keys, map, reduce } from 'ramda';

import { Responsive as ResponsiveHeight } from '@centreon/ui';

import {
  breakpointAtom,
  dashboardAtom,
  getMaxColumnsByBreakpoint,
  isEditingAtom,
  layoutByBreakpointDerivedAtom
} from '../atoms';
import { Breakpoint, ResponsiveLayouts } from '../models';

import EditionGrid from './EditionGrid';
import Widget from './Widget';
import useDashboardStyles from './useDashboardStyles';

const ReactGridLayout = WidthProvider(ResponsiveGridLayout);

const Layout: FC = () => {
  const { classes } = useDashboardStyles();

  const [dashboard, setDashboard] = useAtom(dashboardAtom);
  const layout = useAtomValue(layoutByBreakpointDerivedAtom);
  const isEditing = useAtomValue(isEditingAtom);
  const setBreakpoint = useSetAtom(breakpointAtom);

  const changeLayout = (
    newLayout: Array<Layout>,
    newLayouts: ResponsiveLayouts
  ): void => {
    const currentSortedLayout = map(
      ({ i, w, h, y, x }) => ({ h, i, w, x, y }),
      newLayout
    ).sort((a, b) => a.x + a.y - (b.x + b.y));

    const responsiveLayouts = reduce(
      (acc, key) => {
        return {
          ...acc,
          [key]: currentSortedLayout.map((widget) => ({
            ...newLayouts[key].find(({ i }) => equals(i, widget.i)),
            h: currentSortedLayout.find(({ i }) => equals(i, widget.i))
              ?.h as number,
            w: Math.min(
              currentSortedLayout.find(({ i }) => equals(i, widget.i))
                ?.w as number,
              getMaxColumnsByBreakpoint(key)
            )
          }))
        };
      },
      {},
      keys(newLayouts)
    ) as ResponsiveLayouts;

    setDashboard((currentDashboard) => ({
      layouts: responsiveLayouts,
      settings: currentDashboard.settings
    }));
  };

  return (
    <ResponsiveHeight>
      <Responsive.ParentSize>
        {({ width, height }): JSX.Element => (
          <div className={classes.container}>
            {isEditing && <EditionGrid height={height} width={width} />}
            <ReactGridLayout
              breakpoints={{ lg: 1800, md: 1024, sm: 768 }}
              cols={{ lg: 8, md: 4, sm: 2 }}
              containerPadding={[4, 0]}
              layouts={dashboard.layouts}
              resizeHandles={['s', 'e', 'se']}
              rowHeight={30}
              width={width}
              onBreakpointChange={(newBreakpoint: Breakpoint): void =>
                setBreakpoint(newBreakpoint)
              }
              onLayoutChange={changeLayout}
            >
              {layout.map(({ i }) => {
                return (
                  <div key={i}>
                    <Widget key={i} title={i} />
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
