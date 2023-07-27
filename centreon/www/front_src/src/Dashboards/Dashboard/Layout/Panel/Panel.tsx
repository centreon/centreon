import { useAtomValue, useSetAtom } from 'jotai';

import { RichTextEditor, useMemoComponent } from '@centreon/ui';

import {
  getPanelConfigurationsDerivedAtom,
  getPanelOptionsDerivedAtom,
  setPanelOptionsDerivedAtom
} from '../../atoms';
import FederatedComponent from '../../../../components/FederatedComponents';
import { isGenericText } from '../../utils';

interface Props {
  id: string;
}

const Panel = ({ id }: Props): JSX.Element => {
  const getPanelOptions = useAtomValue(getPanelOptionsDerivedAtom);
  const getPanelConfigurations = useAtomValue(
    getPanelConfigurationsDerivedAtom
  );
  const setPanelOptions = useSetAtom(setPanelOptionsDerivedAtom);

  const panelOptions = getPanelOptions(id);

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
        panelOptions={panelOptions}
        path={panelConfigurations.path}
        setPanelOptions={changePanelOptions}
      />
    ),
    memoProps: [id, panelOptions]
  });
};

export default Panel;
