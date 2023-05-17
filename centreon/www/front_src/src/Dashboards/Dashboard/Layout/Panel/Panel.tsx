import { FC } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';

import { useMemoComponent } from '@centreon/ui';

import {
  getPanelConfigurationsDerivedAtom,
  getPanelOptionsDerivedAtom,
  setPanelOptionsDerivedAtom
} from '../../atoms';
import FederatedComponent from '../../../../components/FederatedComponents';

interface Props {
  id: string;
}

const Panel: FC<Props> = ({ id }) => {
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
  console.log('panelOptions', panelOptions, panelConfigurations)

  return useMemoComponent({
    Component: (
      <div>
        <FederatedComponent
          isFederatedWidget
          id={id}
          panelOptions={panelOptions}
          path={panelConfigurations.path}
          setPanelOptions={changePanelOptions}
        />
      </div>
    ),
    memoProps: [id, panelOptions]
  });
};

export default Panel;
