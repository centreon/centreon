import { useAtomValue, useSetAtom } from 'jotai';

import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import {
  dashboardRefreshIntervalAtom,
  getPanelConfigurationsDerivedAtom,
  getPanelOptionsAndDataDerivedAtom,
  setPanelOptionsAndDataDerivedAtom
} from '../../atoms';
import FederatedComponent from '../../../../components/FederatedComponents';
import { isGenericText } from '../../utils';

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
  const setPanelOptions = useSetAtom(setPanelOptionsAndDataDerivedAtom);

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
        globalRefreshInterval={refreshInterval}
        id={id}
        panelData={panelOptionsAndData?.data}
        panelOptions={panelOptionsAndData?.options}
        path={panelConfigurations?.path}
        refreshCount={refreshCount}
        setPanelOptions={changePanelOptions}
      />
    ),
    memoProps: [id, panelOptionsAndData, refreshCount]
  });
};

export default Panel;
