import { platformVersionsAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { has } from 'ramda';

const useIsBamModuleInstalled = (): boolean => {
  const platform = useAtomValue(platformVersionsAtom);

  const isBamModuleInstalled = has('centreon-bam-server', platform?.modules);

  return isBamModuleInstalled;
};

export default useIsBamModuleInstalled;
