import { useRef } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { useBlocker } from 'react-router-dom';
import { equals } from 'ramda';

import { isEditingAtom, isRedirectionBlockedAtom } from '../atoms';
import { federatedWidgetsAtom } from '../../../../federatedModules/atoms';
import { DashboardPanel } from '../../../api/models';

import useDashboardDirty from './useDashboardDirty';
import { formatPanel } from './useDashboardDetails';

export interface UseDashboardSaveBlockerState {
  blockNavigation?: () => void;
  blocked: boolean;
  proceedNavigation?: () => void;
}

export const saveBlockerHooks = {
  useBlocker
};

const useDashboardSaveBlocker = (
  panels?: Array<DashboardPanel>
): UseDashboardSaveBlockerState => {
  const isEditing = useAtomValue(isEditingAtom);
  const federatedWidgets = useAtomValue(federatedWidgetsAtom);
  const setIsRedirectionBlockedAtom = useSetAtom(isRedirectionBlockedAtom);

  const dirty = useDashboardDirty(
    (panels || []).map((panel) =>
      formatPanel({ federatedWidgets, panel, staticPanel: false })
    )
  );

  const blocker = saveBlockerHooks.useBlocker(
    ({ currentLocation, nextLocation }) =>
      isEditing &&
      dirty &&
      !equals(currentLocation.pathname, nextLocation.pathname)
  );

  const previousBlockedStateRef = useRef({
    blocked: equals(blocker.state, 'blocked'),
    isEditing
  });

  const currentBlockedState = equals(blocker.state, 'blocked');

  if (
    (!equals(previousBlockedStateRef.current.blocked, currentBlockedState) ||
      !equals(previousBlockedStateRef.current.isEditing, isEditing)) &&
    dirty
  ) {
    previousBlockedStateRef.current.blocked = currentBlockedState;
    previousBlockedStateRef.current.isEditing = isEditing;
    setIsRedirectionBlockedAtom(equals(blocker.state, 'blocked'));
  }

  return {
    blockNavigation: blocker.reset,
    blocked: equals(blocker.state, 'blocked'),
    proceedNavigation: blocker.proceed
  };
};

export default useDashboardSaveBlocker;
