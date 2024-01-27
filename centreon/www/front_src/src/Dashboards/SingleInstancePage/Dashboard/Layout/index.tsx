import { useMemo } from 'react';

import { Layout } from 'react-grid-layout';
import { useAtom, useAtomValue } from 'jotai';
import { equals, isEmpty, map, propEq } from 'ramda';

import { getColumnsFromScreenSize } from '@centreon/ui';

import { dashboardAtom, isEditingAtom, refreshCountsAtom } from '../atoms';
import { Panel } from '../models';
import { editProperties } from '../hooks/useCanEditDashboard';
import { AddEditWidgetModal } from '../AddEditWidget';
import useLinkToResourceStatus from '../hooks/useLinkToResourceStatus';

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

  const { canEdit } = editProperties.useCanEditProperties();

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

  const { getLinkToResourceStatusPage, changeViewMode } =
    useLinkToResourceStatus();

  return (
    <>
      <PanelsLayout
        displayMoreActions
        canEdit={canEdit}
        changeLayout={changeLayout}
        changeViewMode={changeViewMode}
        getLinkToResourceStatusPage={getLinkToResourceStatusPage}
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
