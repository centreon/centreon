import { useAtomValue, useSetAtom } from 'jotai';

import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import {
  getPanelConfigurationsDerivedAtom,
  getPanelOptionsAndDataDerivedAtom,
  setPanelOptionsAndDataDerivedAtom
} from '../../atoms';
import FederatedComponent from '../../../../components/FederatedComponents';
import { isGenericText } from '../../utils';

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
  const setPanelOptions = useSetAtom(setPanelOptionsAndDataDerivedAtom);

  const panelOptionsAndData = getPanelOptionsAndData(id);

  const panelConfigurations = getPanelConfigurations(id);

  const changePanelOptions = (newPanelOptions): void => {
    setPanelOptions({ id, options: newPanelOptions });
  };

  return useMemoComponent({
    Component: isGenericText(panelConfigurations.path) ? (
      <RichTextEditor
        editable={false}
        editorState={
          (panelOptions as { genericText: string | undefined })?.genericText
        }
      />
    ) : (
      <FederatedComponent
        isFederatedWidget
        id={id}
        panelData={panelOptionsAndData?.data}
        panelOptions={panelOptionsAndData?.options}
        path={panelConfigurations.path}
        setPanelOptions={changePanelOptions}
      />
    ),
    memoProps: [id, panelOptionsAndData]
  });
};

export default Panel;
