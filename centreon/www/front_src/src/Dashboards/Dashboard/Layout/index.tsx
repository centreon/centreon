import { useMemo } from 'react';

import { Layout } from 'react-grid-layout';
import { useAtom, useAtomValue } from 'jotai';
import { equals, isEmpty, map, propEq } from 'ramda';

import { DashboardLayout, getColumnsFromScreenSize } from '@centreon/ui';

import { dashboardAtom, isEditingAtom, refreshCountsAtom } from '../atoms';
import { Panel } from '../models';
import { AddEditWidgetModal, AddWidgetPanel } from '../AddEditWidget';
import { useCanEditProperties } from '../hooks/useCanEditDashboard';
import useLinkToResourceStatus from '../hooks/useLinkToResourceStatus';

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
  const [refreshCounts, setRefreshCounts] = useAtom(refreshCountsAtom);
  const isEditing = useAtomValue(isEditingAtom);

  const { canEdit } = useCanEditProperties();

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
        data: currentWidget?.data,
        name: currentWidget?.name,
        options: currentWidget?.options,
        panelConfiguration: currentWidget?.panelConfiguration
      } as Panel;
    }, layout);

    setDashboard({
      layout: newLayout
    });
  };

  const setRefreshCount = (id: string): void => {
    setRefreshCounts((prev) => ({
      ...prev,
      [id]: (prev[id] || 0) + 1
    }));
  };

  const showDefaultLayout = useMemo(
    () => isEmpty(dashboard.layout) && isEditing,
    [dashboard.layout, isEditing]
  );

  const panels = showDefaultLayout
    ? emptyLayout
    : dashboard.layout.map(({ i, ...props }) => {
        return {
          i,
          refreshCount: refreshCounts[i] || 0,
          ...props
        };
      });

  const { getLinkToResourceStatusPage } = useLinkToResourceStatus();

  return (
    <>
      <DashboardLayout.Layout
        changeLayout={changeLayout}
        displayGrid={isEditing}
        isStatic={!isEditing || showDefaultLayout}
        layout={panels}
      >
        {panels.map(
          ({ i, panelConfiguration, refreshCount, data, name, options }) => (
            <DashboardLayout.Item
              canMove={
                canEdit && isEditing && !panelConfiguration?.isAddWidgetPanel
              }
              disablePadding={panelConfiguration?.isAddWidgetPanel}
              header={
                !panelConfiguration?.isAddWidgetPanel ? (
                  <PanelHeader
                    id={i}
                    linkToResourceStatus={getLinkToResourceStatusPage(
                      data,
                      name,
                      options
                    )}
                    setRefreshCount={setRefreshCount}
                    widgetName={name}
                  />
                ) : undefined
              }
              id={i}
              key={i}
            >
              {panelConfiguration?.isAddWidgetPanel ? (
                <AddWidgetPanel />
              ) : (
                <DashboardPanel id={i} refreshCount={refreshCount} />
              )}
            </DashboardLayout.Item>
          )
        )}
      </DashboardLayout.Layout>
      <AddEditWidgetModal />
    </>
  );
};

export default Layout;
