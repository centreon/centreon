import { useMemo } from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { equals, isEmpty, map, propEq } from 'ramda';
import { Layout } from 'react-grid-layout';

import { getColumnsFromScreenSize } from '@centreon/ui';

import { AddEditWidgetModal } from '../AddEditWidget';
import { dashboardAtom, isEditingAtom, refreshCountsAtom } from '../atoms';
import { useCanEditProperties } from '../hooks/useCanEditDashboard';
import { Panel } from '../models';

import PanelsLayout from './Layout';

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

const DashboardPageLayout = (): JSX.Element => {
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
      const currentWidget = dashboard.layout.find(propEq(panel.i, 'i'));

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

  return (
    <>
      <PanelsLayout
        displayMoreActions
        canEdit={canEdit}
        changeLayout={changeLayout}
        isEditing={isEditing}
        isStatic={!isEditing || showDefaultLayout}
        panels={panels}
        setRefreshCount={setRefreshCount}
      />
      <AddEditWidgetModal />
    </>
  );
};

export default DashboardPageLayout;
