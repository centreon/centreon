import { Layout } from 'react-grid-layout';
import { useAtom, useAtomValue } from 'jotai';
import { equals, map, propEq } from 'ramda';

import {
  DashboardLayout,
  DashboardItem,
  getColumnsFromScreenSize
} from '@centreon/ui';

import { dashboardAtom, isEditingAtom } from '../atoms';
import { Panel } from '../models';

import DashboardPanel from './Panel/Panel';

const Layout = (): JSX.Element => {
  const [dashboard, setDashboard] = useAtom(dashboardAtom);
  const isEditing = useAtomValue(isEditingAtom);

  const changeLayout = (layout: Array<Layout>): void => {
    const isOneColumnDisplay = equals(getColumnsFromScreenSize(), 1);
    if (isOneColumnDisplay) {
      return;
    }

    const newLayout = map<Layout, Panel>((panel) => {
      const currentWidget = dashboard.layout.find(propEq('i', panel.i));

      return {
        ...panel,
        options: currentWidget?.options,
        panelConfiguration: currentWidget?.panelConfiguration
      } as Panel;
    }, layout);

    setDashboard({
      layout: newLayout
    });
  };

  return (
    <DashboardLayout
      changeLayout={changeLayout}
      displayGrid={isEditing}
      layout={dashboard.layout}
    >
      {dashboard.layout.map(({ i }) => {
        return (
          <DashboardItem key={i}>
            <DashboardPanel id={i} />
          </DashboardItem>
        );
      })}
    </DashboardLayout>
  );
};

export default Layout;
