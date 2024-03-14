import { useAtomValue } from 'jotai';
import { useBlocker } from 'react-router-dom';
import { equals } from 'ramda';

import { isEditingAtom } from '../atoms';

export interface UseDashboardSaveBlockerState {
  blockNavigation?: () => void;
  blocked: boolean;
  proceedNavigation?: () => void;
}

export const router = {
  useBlocker
};

const useDashboardSaveBlocker = (): UseDashboardSaveBlockerState => {
  const isEditing = useAtomValue(isEditingAtom);

  const blocker = router.useBlocker(isEditing);

  return {
    blockNavigation: blocker.reset,
    blocked: equals(blocker.state, 'blocked'),
    proceedNavigation: blocker.proceed
  };
};

export default useDashboardSaveBlocker;
