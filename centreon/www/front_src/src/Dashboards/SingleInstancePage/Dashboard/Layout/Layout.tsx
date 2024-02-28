import { Layout } from 'react-grid-layout';

import { DashboardLayout } from '@centreon/ui';

import { AddWidgetPanel } from '../AddEditWidget';
import { Panel } from '../models';
import useLinkToResourceStatus from '../hooks/useLinkToResourceStatus';

import DashboardPanel from './Panel/Panel';
import PanelHeader from './Panel/PanelHeader';

interface Props {
  canEdit?: boolean;
  changeLayout?: (newLayout: Array<Layout>) => void;
  displayMoreActions?: boolean;
  isEditing?: boolean;
  isStatic: boolean;
  panels: Array<Panel>;
  setRefreshCount?: (id) => void;
}

const PanelsLayout = ({
  isEditing,
  panels,
  isStatic,
  changeLayout,
  canEdit,
  setRefreshCount,
  displayMoreActions = true
}: Props): JSX.Element => {
  const { getLinkToResourceStatusPage, changeViewMode } =
    useLinkToResourceStatus();

  return (
    <DashboardLayout.Layout
      changeLayout={changeLayout}
      displayGrid={isEditing}
      isStatic={isStatic}
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
                  changeViewMode={() => changeViewMode(options?.displayType)}
                  displayMoreActions={displayMoreActions}
                  id={i}
                  linkToResourceStatus={
                    data?.resources
                      ? getLinkToResourceStatusPage(data, name, options)
                      : undefined
                  }
                  setRefreshCount={setRefreshCount}
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
  );
};

export default PanelsLayout;
