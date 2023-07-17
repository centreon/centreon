import { useAtomValue, useSetAtom } from 'jotai';

import { useMemoComponent } from '@centreon/ui';

import {
  getPanelConfigurationsDerivedAtom,
  getPanelOptionsDerivedAtom,
  setPanelOptionsDerivedAtom
} from '../../atoms';
import FederatedComponent from '../../../../components/FederatedComponents';
import { AddWidgetPanel } from '../../AddWidget';

interface Props {
  id: string;
  isAddWidgetPanel?: boolean;
}

const Panel = ({ id, isAddWidgetPanel }: Props): JSX.Element => {
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
    Component: isAddWidgetPanel ? (
      <AddWidgetPanel />
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
