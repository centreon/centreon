import { createStore } from 'jotai';

import Module from '.';
import LicensedModule from './LicensedModule';

interface Props {
  isFederatedComponent?: boolean;
  /** Mocked as valid or invalid by the specs. */
  moduleName: string;
}

export const Default = (): JSX.Element => (
  <Module seedName="seed" store={createStore()}>
    <p>Module</p>
  </Module>
);

export const Licensed = ({
  isFederatedComponent = false,
  moduleName
}: Props): JSX.Element => (
  <div style={{ height: '100vh' }}>
    <LicensedModule
      isFederatedComponent={isFederatedComponent}
      moduleName={moduleName}
      seedName="seed"
      store={createStore()}
    >
      <p>Module</p>
    </LicensedModule>
  </div>
);
