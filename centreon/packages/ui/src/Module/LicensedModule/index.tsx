import Module, { ModuleProps } from '../index';

import LicenseCheck, { LicenseCheckProps } from './LicenseCheck';

type Props = ModuleProps & LicenseCheckProps;

const LicensedModule = ({
  isFederatedComponent,
  moduleName,
  children,
  ...props
}: Props): JSX.Element => {
  return (
    <Module {...props}>
      <LicenseCheck
        isFederatedComponent={isFederatedComponent}
        moduleName={moduleName}
      >
        {children}
      </LicenseCheck>
    </Module>
  );
};

export default LicensedModule;
