import { FC } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';

import { useMemoComponent } from '@centreon/ui';

import {
  getPanelConfigurationsDerivedAtom,
  getPanelOptionsDerivedAtom,
  setPanelOptionsDerivedAtom
} from '../../atoms';
import FederatedComponent from '../../../components/FederatedComponents';

import PanelHeader from './PanelHeader';

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

  return useMemoComponent({
    Component: (
      <div>
        <PanelHeader id={id} />
        <FederatedComponent
          isFederatedWidget
          id={id}
          memoProps={[panelOptions, id]}
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
