import { useMemo } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';

import { RichTextEditor, client, useMemoComponent } from '@centreon/ui';

import FederatedComponent from '../../../../../components/FederatedComponents';
import {
  dashboardRefreshIntervalAtom,
  getPanelConfigurationsDerivedAtom,
  getPanelOptionsAndDataDerivedAtom,
  isEditingAtom,
  setPanelOptionsAndDataDerivedAtom
} from '../../atoms';
import DescriptionWrapper from '../../components/DescriptionWrapper';
import { useCanEditProperties } from '../../hooks/useCanEditDashboard';
import useLinkToResourceStatus from '../../hooks/useLinkToResourceStatus';
import useSaveDashboard from '../../hooks/useSaveDashboard';
import { isGenericText, isRichTextEditorEmpty } from '../../utils';

import { usePanelHeaderStyles } from './usePanelStyles';

interface Props {
  dashboardId: number | string;
  id: string;
  playlistHash?: string;
  refreshCount?: number;
  isInViewport: boolean;
}

const Panel = ({
  id,
  refreshCount,
  playlistHash,
  dashboardId,
  isInViewport
}: Props): JSX.Element => {
  const { classes, cx } = usePanelHeaderStyles();

  const { changeViewMode } = useLinkToResourceStatus();

  const getPanelOptionsAndData = useAtomValue(
    getPanelOptionsAndDataDerivedAtom
  );
  const getPanelConfigurations = useAtomValue(
    getPanelConfigurationsDerivedAtom
  );
  const refreshInterval = useAtomValue(dashboardRefreshIntervalAtom);
  const isEditing = useAtomValue(isEditingAtom);
  const setPanelOptions = useSetAtom(setPanelOptionsAndDataDerivedAtom);

  const { canEditField } = useCanEditProperties();
  const { saveDashboard } = useSaveDashboard();

  const panelOptionsAndData = getPanelOptionsAndData(id);

  const panelConfigurations = getPanelConfigurations(id);

  const widgetPrefixQuery = useMemo(
    () => `${panelConfigurations.path}_${id}`,
    [panelConfigurations.path, id]
  );

  const changePanelOptions = (partialOptions: object): void => {
    setPanelOptions({
      data: panelOptionsAndData?.data,
      id,
      options: { ...panelOptionsAndData?.options, ...partialOptions }
    });
  };

  const displayDescription =
    panelOptionsAndData.options?.description?.enabled &&
    panelOptionsAndData.options?.description?.content &&
    !isRichTextEditorEmpty(panelOptionsAndData.options?.description?.content);

  const isGenericTextPanel = isGenericText(panelConfigurations?.path);

  const getDescription = (): JSX.Element | null => {
    if (!displayDescription) {
      return null;
    }

    if (isGenericTextPanel) {
      return (
        <RichTextEditor
          disabled
          contentClassName={cx(isGenericTextPanel && classes.description)}
          editable={false}
          editorState={
            panelOptionsAndData.options?.description?.content || undefined
          }
        />
      );
    }

    return (
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
    );
  };

  return useMemoComponent({
    Component: (
      <>
        {getDescription()}
        {!isGenericTextPanel && (
          <div
            className={cx(
              displayDescription
                ? classes.panelContentWithDescription
                : classes.panelContent
            )}
          >
            <FederatedComponent
              isFederatedWidget
              canEdit={canEditField}
              changeViewMode={changeViewMode}
              dashboardId={dashboardId}
              globalRefreshInterval={refreshInterval}
              hasDescription={displayDescription}
              id={id}
              isEditingDashboard={isEditing}
              isInViewport={isInViewport}
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
          </div>
        )}
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
      dashboardId,
      isInViewport
    ]
  });
};

export default Panel;
