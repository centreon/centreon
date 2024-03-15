import { useRef } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { useBlocker } from 'react-router-dom';
import { equals } from 'ramda';

import { isEditingAtom, isRedirectionBlockedAtom } from '../atoms';

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
  const setIsRedirectionBlockedAtom = useSetAtom(isRedirectionBlockedAtom);

  const blocker = router.useBlocker(
    ({ currentLocation, nextLocation }) =>
      isEditing && !equals(currentLocation.pathname, nextLocation.pathname)
  );

  const previousBlockedStateRef = useRef(equals(blocker.state, 'blocked'));

  const currentBlockedState = equals(blocker.state, 'blocked');

  if (!equals(previousBlockedStateRef.current, currentBlockedState)) {
    previousBlockedStateRef.current = currentBlockedState;
    setIsRedirectionBlockedAtom(equals(blocker.state, 'blocked'));
  }

  return {
    blockNavigation: blocker.reset,
    blocked: equals(blocker.state, 'blocked'),
    proceedNavigation: blocker.proceed
  };
};

export default useDashboardSaveBlocker;
