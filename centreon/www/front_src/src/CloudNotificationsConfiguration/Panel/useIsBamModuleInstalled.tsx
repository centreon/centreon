import { useAtomValue } from 'jotai';
import { has } from 'ramda';

import { platformVersionsAtom } from '@centreon/ui-context';

const useIsBamModuleInstalled = (): boolean => {
  const platform = useAtomValue(platformVersionsAtom);

  const isBamModuleInstalled = has('centreon-bam-server', platform?.modules);

  return isBamModuleInstalled;
};

export default useIsBamModuleInstalled;
