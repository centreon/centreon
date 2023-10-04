import { useAtomValue, useSetAtom } from 'jotai';

import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import {
  dashboardRefreshIntervalAtom,
  getPanelConfigurationsDerivedAtom,
  getPanelOptionsAndDataDerivedAtom,
  isEditingAtom,
  setPanelOptionsAndDataDerivedAtom
} from '../../atoms';
import FederatedComponent from '../../../../components/FederatedComponents';
import { isGenericText } from '../../utils';
import { editProperties } from '../../useCanEditDashboard';
import useSaveDashboard from '../../useSaveDashboard';

interface Props {
  id: string;
  refreshCount?: number;
}

const Panel = ({ id, refreshCount }: Props): JSX.Element => {
  const getPanelOptionsAndData = useAtomValue(
    getPanelOptionsAndDataDerivedAtom
  );
  const getPanelConfigurations = useAtomValue(
    getPanelConfigurationsDerivedAtom
  );
  const refreshInterval = useAtomValue(dashboardRefreshIntervalAtom);
  const isEditing = useAtomValue(isEditingAtom);
  const setPanelOptions = useSetAtom(setPanelOptionsAndDataDerivedAtom);

  const { canEditField } = editProperties.useCanEditProperties();
  const { saveDashboard } = useSaveDashboard();

  const panelOptionsAndData = getPanelOptionsAndData(id);

  const panelConfigurations = getPanelConfigurations(id);

  const changePanelOptions = (newPanelOptions): void => {
    setPanelOptions({ id, options: newPanelOptions });
  };

  return useMemoComponent({
    Component: isGenericText(panelConfigurations?.path) ? (
      <RichTextEditor
        editable={false}
        editorState={
          panelOptionsAndData.options?.description?.enabled
            ? panelOptionsAndData.options?.description?.content
            : undefined
        }
      />
    ) : (
      <FederatedComponent
        isFederatedWidget
        canEdit={canEditField}
        globalRefreshInterval={refreshInterval}
        id={id}
        isEditing={isEditing}
        panelData={panelOptionsAndData?.data}
        panelOptions={panelOptionsAndData?.options}
        path={panelConfigurations?.path}
        refreshCount={refreshCount}
        saveDashboard={saveDashboard}
        setPanelOptions={changePanelOptions}
      />
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
