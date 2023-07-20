import { useMemo } from 'react';

import { Layout } from 'react-grid-layout';
import { useAtom, useAtomValue } from 'jotai';
import { equals, isEmpty, map, propEq } from 'ramda';

import { DashboardLayout, getColumnsFromScreenSize } from '@centreon/ui';

import { dashboardAtom, isEditingAtom } from '../atoms';
import { Panel } from '../models';

import DashboardPanel from './Panel/Panel';
import PanelHeader from './Panel/PanelHeader';

const addWidgetId = 'add_widget_panel';

const emptyLayout: Array<Panel> = [
  {
    h: 3,
    i: addWidgetId,
    name: addWidgetId,
    panelConfiguration: {
      isAddWidgetPanel: true,
      path: ''
    },
    static: true,
    w: 3,
    x: 0,
    y: 0
  }
];

const Layout = (): JSX.Element => {
  const [dashboard, setDashboard] = useAtom(dashboardAtom);
  const isEditing = useAtomValue(isEditingAtom);

  const changeLayout = (layout: Array<Layout>): void => {
    const isOneColumnDisplay = equals(getColumnsFromScreenSize(), 1);
    const isEmptyLayout =
      equals(layout.length, 1) && equals(layout[0].i, addWidgetId);

    if (isOneColumnDisplay || isEmptyLayout) {
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

  const showDefaultLayout = useMemo(
    () => isEmpty(dashboard.layout) && isEditing,
    [dashboard.layout, isEditing]
  );

  const panels = showDefaultLayout ? emptyLayout : dashboard.layout;

  return (
    <DashboardLayout.Layout
      changeLayout={changeLayout}
      displayGrid={isEditing}
      layout={panels}
    >
      {panels.map(({ i, panelConfiguration }) => {
        return (
          <DashboardLayout.Item
            disablePadding={panelConfiguration?.isAddWidgetPanel}
            header={
              isEditing && !panelConfiguration?.isAddWidgetPanel ? (
                <PanelHeader id={i} />
              ) : undefined
            }
            id={i}
            key={i}
          >
            <DashboardPanel
              id={i}
              isAddWidgetPanel={panelConfiguration?.isAddWidgetPanel}
            />
          </DashboardLayout.Item>
        );
      })}
    </DashboardLayout.Layout>
  );
};

export default Layout;
