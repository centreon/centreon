import { Suspense, useMemo } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { useSearchParams } from 'react-router-dom';

import {
  LoadingSkeleton,
  RichTextEditor,
  client,
  useMemoComponent
} from '@centreon/ui';

import FederatedComponent from '../../../../../components/FederatedComponents';
import {
  dashboardRefreshIntervalAtom,
  getPanelConfigurationsDerivedAtom,
  getPanelOptionsAndDataDerivedAtom,
  isEditingAtom,
  setPanelOptionsAndDataDerivedAtom,
  switchPanelsEditionModeDerivedAtom
} from '../../atoms';
import DescriptionWrapper from '../../components/DescriptionWrapper';
import { useCanEditProperties } from '../../hooks/useCanEditDashboard';
import useLinkToResourceStatus from '../../hooks/useLinkToResourceStatus';
import useSaveDashboard from '../../hooks/useSaveDashboard';
import { isGenericText, isRichTextEditorEmpty } from '../../utils';

import { equals, find, isEmpty, isNil } from 'ramda';
import { internalWidgetComponents } from '../../Widgets/widgets';
import { usePanelHeaderStyles } from './usePanelStyles';

interface Props {
  dashboardId: number | string;
  id: string;
  name: string;
  playlistHash?: string;
  refreshCount?: number;
}

const Panel = ({
  id,
  name,
  refreshCount,
  playlistHash,
  dashboardId
}: Props): JSX.Element => {
  const { classes, cx } = usePanelHeaderStyles();

  const { changeViewMode } = useLinkToResourceStatus();

  const [searchParams, setSearchParams] = useSearchParams(
    window.location.search
  );

  const getPanelOptionsAndData = useAtomValue(
    getPanelOptionsAndDataDerivedAtom
  );
  const getPanelConfigurations = useAtomValue(
    getPanelConfigurationsDerivedAtom
  );
  const refreshInterval = useAtomValue(dashboardRefreshIntervalAtom);
  const isEditing = useAtomValue(isEditingAtom);
  const setPanelOptions = useSetAtom(setPanelOptionsAndDataDerivedAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );

  const { canEditField } = useCanEditProperties();
  const { saveDashboard } = useSaveDashboard();

  const panelOptionsAndData = getPanelOptionsAndData(id);

  const panelConfigurations = getPanelConfigurations(id);

  const { Component, remoteEntry } =
    find(
      (widget) => equals(widget.moduleName, name),
      internalWidgetComponents
    ) || {};

  const widgetPrefixQuery = useMemo(
    () => `${panelConfigurations.path}_${id}`,
    [panelConfigurations.path, id]
  );

  const changePanelOptions = (partialOptions: object): void => {
    switchPanelsEditionMode(true);
    searchParams.set('edit', 'true');
    setSearchParams(searchParams);

    setPanelOptions({
      data: panelOptionsAndData?.data,
      id,
      options: { ...panelOptionsAndData?.options, ...partialOptions }
    });
  };

  const isGenericTextPanel = isGenericText(panelConfigurations?.path);

  const displayDescription =
    !isGenericTextPanel &&
    panelOptionsAndData.options?.description?.enabled &&
    panelOptionsAndData.options?.description?.content &&
    !isRichTextEditorEmpty(panelOptionsAndData.options?.description?.content);

  return useMemoComponent({
    Component: (
      <>
        {displayDescription && (
          <DescriptionWrapper>
            <RichTextEditor
              disabled
              contentClassName={cx(isGenericTextPanel && classes.description)}
              editable={false}
              editorState={
                panelOptionsAndData.options?.description?.content || undefined
              }
              inputClassname={classes.descriptionInput}
            />
          </DescriptionWrapper>
        )}
        <div
          className={cx(
            displayDescription
              ? classes.panelContentWithDescription
              : classes.panelContent
          )}
        >
          {!isEmpty(remoteEntry) || isNil(Component) ? (
            <FederatedComponent
              isFederatedWidget
              canEdit={canEditField}
              changeViewMode={changeViewMode}
              dashboardId={dashboardId}
              globalRefreshInterval={refreshInterval}
              hasDescription={displayDescription}
              id={id}
              isEditingDashboard={isEditing}
              panelData={panelOptionsAndData?.data}
              panelOptions={panelOptionsAndData?.options}
              path={panelConfigurations.path}
              playlistHash={playlistHash}
              queryClient={client}
              refreshCount={refreshCount}
              saveDashboard={saveDashboard}
              setPanelOptions={changePanelOptions}
              widgetPrefixQuery={widgetPrefixQuery}
            />
          ) : (
            <Suspense
              fallback={
                <LoadingSkeleton
                  variant="rectangular"
                  width="100%"
                  height="100%"
                />
              }
            >
              <Component
                canEdit={canEditField}
                changeViewMode={changeViewMode}
                dashboardId={dashboardId}
                globalRefreshInterval={refreshInterval}
                hasDescription={displayDescription}
                isEditingDashboard={isEditing}
                panelData={panelOptionsAndData?.data}
                panelOptions={panelOptionsAndData?.options}
                path={panelConfigurations.path}
                playlistHash={playlistHash}
                queryClient={client}
                refreshCount={refreshCount}
                saveDashboard={saveDashboard}
                setPanelOptions={changePanelOptions}
                widgetPrefixQuery={widgetPrefixQuery}
              />
            </Suspense>
          )}
        </div>
      </>
    ),
    memoProps: [
      id,
      panelOptionsAndData,
      refreshCount,
      isEditing,
      refreshInterval,
      canEditField,
      playlistHash,
      dashboardId
    ]
  });
};

export default Panel;
