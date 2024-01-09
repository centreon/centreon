import { Layout } from 'react-grid-layout';

import { DashboardLayout } from '@centreon/ui';

import { AddWidgetPanel } from '../AddEditWidget';
import { Panel } from '../models';

import DashboardPanel from './Panel/Panel';
import PanelHeader from './Panel/PanelHeader';

interface Props {
  canEdit?: boolean;
  changeLayout?: (newLayout: Array<Layout>) => void;
  displayMoreActions?: boolean;
  getLinkToResourceStatusPage: (data, name, options) => string;
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
  displayMoreActions = true,
  getLinkToResourceStatusPage
}: Props): JSX.Element => {
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
                  displayMoreActions={displayMoreActions}
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
  );
};

export default PanelsLayout;
