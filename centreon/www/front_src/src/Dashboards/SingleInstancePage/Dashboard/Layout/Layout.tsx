import { equals, isEmpty, isNil, lte } from 'ramda';
import type { Layout } from 'react-grid-layout';

import { DashboardLayout } from '@centreon/ui';

import { AddWidgetPanel } from '../AddEditWidget';
import useLinkToResourceStatus from '../hooks/useLinkToResourceStatus';
import type { Panel } from '../models';

import { useAtomValue } from 'jotai';
import { federatedWidgetsPropertiesAtom } from '../../../../federatedModules/atoms';
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

  const federatedWidgetsProperties = useAtomValue(
    federatedWidgetsPropertiesAtom
  );

  return (
    <DashboardLayout.Layout
      additionalMemoProps={[dashboardId]}
      changeLayout={changeLayout}
      displayGrid={isEditing}
      isStatic={isStatic}
      layout={panels}
    >
      {panels.map(
        ({ i, panelConfiguration, refreshCount, data, name, options, w }) => (
          <DashboardLayout.Item
            additionalMemoProps={[dashboardId, panelConfiguration.path]}
            canMove={
              canEdit && isEditing && !panelConfiguration?.isAddWidgetPanel
            }
            disablePadding={panelConfiguration?.isAddWidgetPanel}
            header={(headerData) => {
              const enableExpand = Boolean(
                federatedWidgetsProperties.find(({ moduleName }) =>
                  equals(moduleName, name)
                )?.canExpand
              );
              const expandableData = !enableExpand ? undefined : headerData;

              return (
                <>
                  {!panelConfiguration?.isAddWidgetPanel && (
                    <PanelHeader
                      changeViewMode={() =>
                        changeViewMode(options?.displayType)
                      }
                      displayMoreActions={displayMoreActions}
                      displayShrinkRefresh={
                        lte(w, 6) &&
                        !isNil(options?.name) &&
                        !isEmpty(options?.name)
                      }
                      forceDisplayShrinkRefresh={
                        lte(w, 4) &&
                        !isNil(options?.name) &&
                        !isEmpty(options?.name)
                      }
                      id={i}
                      linkToResourceStatus={
                        data?.resources
                          ? getLinkToResourceStatusPage(data, name, options)
                          : undefined
                      }
                      pageType={getPageType(data)}
                      setRefreshCount={setRefreshCount}
                      name={name}
                      expandableData={expandableData}
                    />
                  )}
                </>
              );
            }}
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
                name={name}
              />
            )}
          </DashboardLayout.Item>
        )
      )}
    </DashboardLayout.Layout>
  );
};

export default PanelsLayout;
