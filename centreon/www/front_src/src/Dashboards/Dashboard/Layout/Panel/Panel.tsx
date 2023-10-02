import { useAtomValue, useSetAtom } from 'jotai';

import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import {
  getPanelConfigurationsDerivedAtom,
  getPanelOptionsAndDataDerivedAtom,
  isEditingAtom,
  refreshIntervalAtom,
  setPanelOptionsAndDataDerivedAtom
} from '../../atoms';
import FederatedComponent from '../../../../components/FederatedComponents';
import { editProperties } from '../../useCanEditDashboard';
import useSaveDashboard from '../../useSaveDashboard';

interface Props {
  id: string;
}

const Panel = ({ id }: Props): JSX.Element => {
  const getPanelOptionsAndData = useAtomValue(
    getPanelOptionsAndDataDerivedAtom
  );
  const getPanelConfigurations = useAtomValue(
    getPanelConfigurationsDerivedAtom
  );
  const refreshInterval = useAtomValue(refreshIntervalAtom);
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
    Component: (
      <>
        {panelOptionsAndData.options?.description?.enabled && (
          <RichTextEditor
            disabled
            editable={false}
            editorState={
              panelOptionsAndData.options?.description?.enabled
                ? panelOptionsAndData.options?.description?.content
                : undefined
            }
          />
        )}
        <FederatedComponent
          isFederatedWidget
          canEdit={canEditField}
          globalRefreshInterval={refreshInterval}
          id={id}
          isEditing={isEditing}
          panelData={panelOptionsAndData?.data}
          panelOptions={panelOptionsAndData?.options}
          path={panelConfigurations.path}
          saveDashboard={saveDashboard}
          setPanelOptions={changePanelOptions}
        />
      </>
    ),
    memoProps: [
      id,
      panelOptionsAndData,
      isEditing,
      refreshInterval,
      canEditField
    ]
  });
};

export default Panel;
