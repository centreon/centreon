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
  dashboardId: number | string;
  displayMoreActions?: boolean;
  isEditing?: boolean;
  isStatic: boolean;
  panels: Array<Panel>;
  playlistHash?: string;
  setRefreshCount?: (id) => void;
}

const PanelsLayout = ({
  isEditing,
  panels,
  isStatic,
  changeLayout,
  canEdit,
  setRefreshCount,
  displayMoreActions = true,
  playlistHash,
  dashboardId
}: Props): JSX.Element => {
  const { getLinkToResourceStatusPage, changeViewMode, getPageType } =
    useLinkToResourceStatus();

  return (
    <DashboardLayout.Layout
      additionalMemoProps={[dashboardId]}
      changeLayout={changeLayout}
      displayGrid={isEditing}
      isStatic={isStatic}
      layout={panels}
    >
      {panels.map(
        ({ i, panelConfiguration, refreshCount, data, name, options }) => (
          <DashboardLayout.Item
            additionalMemoProps={[dashboardId, panelConfiguration.path]}
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
                      ? getLinkToResourceStatusPage(data, name)
                      : undefined
                  }
                  pageType={getPageType(data)}
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
              <DashboardPanel
                dashboardId={dashboardId}
                id={i}
                playlistHash={playlistHash}
                refreshCount={refreshCount}
              />
            )}
          </DashboardLayout.Item>
        )
      )}
    </DashboardLayout.Layout>
  );
};

export default PanelsLayout;
