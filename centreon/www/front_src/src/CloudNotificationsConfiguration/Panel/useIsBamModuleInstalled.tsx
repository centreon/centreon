import { has } from 'ramda';
import { useAtomValue } from 'jotai';

import { platformVersionsAtom } from '../../Main/atoms/platformVersionsAtom';

const useIsBamModuleInstalled = (): boolean => {
  const platform = useAtomValue(platformVersionsAtom);

  const isBamModuleInstalled = has('centreon-bam-server', platform?.modules);

  return isBamModuleInstalled;
};

export default useIsBamModuleInstalled;
