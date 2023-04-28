import { FC } from 'react';

import { Responsive } from '@visx/visx';
import {
  WidthProvider,
  Responsive as ResponsiveGridLayout
} from 'react-grid-layout';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { equals, find, map, propEq } from 'ramda';

import { Responsive as ResponsiveHeight } from '@centreon/ui';

import {
  breakpointAtom,
  dashboardAtom,
  isEditingAtom,
  layoutByBreakpointDerivedAtom
} from '../atoms';

import 'react-grid-layout/css/styles.css';

import { Breakpoint } from '../models';

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

  const changeLayout = (_, newLayouts): void => {
    // console.log(newLayouts);
    setDashboard((currentDashboard) => ({
      layouts: newLayouts,
      settings: currentDashboard.settings
    }));
  };

  console.log(dashboard);

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
