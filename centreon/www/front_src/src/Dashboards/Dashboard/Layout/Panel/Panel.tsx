import { useAtomValue, useSetAtom } from 'jotai';
import { useSearchParams } from 'react-router-dom';

import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import {
  dashboardRefreshIntervalAtom,
  getPanelConfigurationsDerivedAtom,
  getPanelOptionsAndDataDerivedAtom,
  isEditingAtom,
  setPanelOptionsAndDataDerivedAtom,
  switchPanelsEditionModeDerivedAtom
} from '../../atoms';
import FederatedComponent from '../../../../components/FederatedComponents';
import { useCanEditProperties } from '../../hooks/useCanEditDashboard';
import useSaveDashboard from '../../hooks/useSaveDashboard';
import { isGenericText, isRichTextEditorEmpty } from '../../utils';
import useLinkToResourceStatus from '../../hooks/useLinkToResourceStatus';

import { usePanelHeaderStyles } from './usePanelStyles';

interface Props {
  id: string;
  refreshCount?: number;
}

const Panel = ({ id, refreshCount }: Props): JSX.Element => {
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

  const displayDescription =
    panelOptionsAndData.options?.description?.enabled &&
    panelOptionsAndData.options?.description?.content &&
    !isRichTextEditorEmpty(panelOptionsAndData.options?.description?.content);

  const isGenericTextPanel = isGenericText(panelConfigurations.path);

  return useMemoComponent({
    Component: (
      <>
        {displayDescription && (
          <RichTextEditor
            disabled
            contentClassName={cx(isGenericTextPanel && classes.description)}
            editable={false}
            editorState={
              panelOptionsAndData.options?.description?.enabled
                ? panelOptionsAndData.options?.description?.content || undefined
                : undefined
            }
          />
        )}
        {!isGenericText(panelConfigurations.path) && (
          <div className={classes.panelContent}>
            <FederatedComponent
              isFederatedWidget
              canEdit={canEditField}
              changeViewMode={changeViewMode}
              globalRefreshInterval={refreshInterval}
              id={id}
              isEditingDashboard={isEditing}
              panelData={panelOptionsAndData?.data}
              panelOptions={panelOptionsAndData?.options}
              path={panelConfigurations.path}
              refreshCount={refreshCount}
              saveDashboard={saveDashboard}
              setPanelOptions={changePanelOptions}
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
      canEditField
    ]
  });
};

export default Panel;
