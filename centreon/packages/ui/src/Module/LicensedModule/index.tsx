import Module, { ModuleProps } from '../index';

import LicenseCheck, { LicenseCheckProps } from './LicenseCheck';

type Props = ModuleProps & LicenseCheckProps;

const LicensedModule = ({
  moduleName,
  children,
  ...props
}: Props): JSX.Element => {
  return (
    <Module {...props}>
      <LicenseCheck moduleName={moduleName}>{children}</LicenseCheck>
    </Module>
  );
};

export default LicensedModule;
