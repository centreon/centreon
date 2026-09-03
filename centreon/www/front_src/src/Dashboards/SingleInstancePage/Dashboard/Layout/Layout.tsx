// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { DashboardLayout } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { equals, isEmpty, isNil } from 'ramda';
import { ReactElement } from 'react';
import type { Layout } from 'react-grid-layout';

import { federatedWidgetsPropertiesAtom } from '../../../../federatedModules/atoms';
import { AddWidgetPanel } from '../AddEditWidget';
import useLinkToResourceStatus from '../hooks/useLinkToResourceStatus';
import type { Panel } from '../models';
import ExpandableButton from './Panel/ExpandableButton';
import DashboardPanel from './Panel/Panel';
import PanelHeader from './Panel/PanelHeader';
import PanelLastRefresh from './Panel/PanelLastRefresh';
import PanelMoreActionsButton from './Panel/PanelMoreActionsButton';

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
}: Props): ReactElement => {
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
        ({ i, panelConfiguration, refreshCount, data, name, options }) => {
          const isAddWidgetPanel = panelConfiguration?.isAddWidgetPanel;
          const hasTitle = !isNil(options?.name) && !isEmpty(options?.name);

          const getExpandableData = (headerData) => {
            const enableExpand = Boolean(
              federatedWidgetsProperties.find(({ moduleName }) =>
                equals(moduleName, name)
              )?.canExpand
            );

            return !enableExpand ? undefined : headerData;
          };

          return (
            <DashboardLayout.Item
              additionalMemoProps={[dashboardId, panelConfiguration?.path]}
              canMove={canEdit && isEditing && !isAddWidgetPanel}
              disablePadding={isAddWidgetPanel}
              hasVisibleHeader={isAddWidgetPanel || hasTitle}
              header={
                isAddWidgetPanel || hasTitle
                  ? () => (
                      <>
                        {!isAddWidgetPanel && (
                          <PanelHeader
                            displayMoreActions={displayMoreActions}
                            id={i}
                            name={name}
                          />
                        )}
                      </>
                    )
                  : undefined
              }
              id={i}
              key={i}
              overlayActions={
                isAddWidgetPanel
                  ? undefined
                  : (headerData) => {
                      const expandableData = getExpandableData(headerData);

                      return !displayMoreActions ||
                        expandableData?.isExpanded ? (
                        <ExpandableButton expandableData={expandableData} />
                      ) : (
                        <PanelMoreActionsButton
                          changeViewMode={() =>
                            changeViewMode(options?.displayType)
                          }
                          expandableData={expandableData}
                          id={i}
                          linkToResourceStatus={
                            data?.resources
                              ? getLinkToResourceStatusPage(data, name, options)
                              : undefined
                          }
                          pageType={getPageType(data)}
                          setRefreshCount={setRefreshCount}
                        />
                      );
                    }
              }
              overlayInfo={
                isAddWidgetPanel
                  ? undefined
                  : () => (
                      <PanelLastRefresh
                        id={i}
                        setRefreshCount={setRefreshCount}
                      />
                    )
              }
            >
              {({ isInViewport }) =>
                isAddWidgetPanel ? (
                  <AddWidgetPanel />
                ) : (
                  <DashboardPanel
                    dashboardId={dashboardId}
                    id={i}
                    isInViewport={isInViewport}
                    name={name}
                    playlistHash={playlistHash}
                    refreshCount={refreshCount}
                  />
                )
              }
            </DashboardLayout.Item>
          );
        }
      )}
    </DashboardLayout.Layout>
  );
};

export default PanelsLayout;
