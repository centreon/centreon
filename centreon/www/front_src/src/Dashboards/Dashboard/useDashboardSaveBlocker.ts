import { useEffect } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { unstable_useBlocker } from 'react-router-dom';
import { equals } from 'ramda';

import {
  dashboardAtom,
  isEditingAtom,
  quitWithoutSavedDashboardAtom
} from './atoms';
import { NamedEntity } from './models';

export interface UseDashboardSaveBlockerState {
  blockNavigation?: () => void;
  blocked: boolean;
  proceedNavigation?: () => void;
}

export const router = {
  useBlocker: unstable_useBlocker
};

const useDashboardSaveBlocker = (
  dashboard: Partial<NamedEntity>
): UseDashboardSaveBlockerState => {
  const isEditing = useAtomValue(isEditingAtom);

  const blocker = router.useBlocker(isEditing);

  const { layout } = useAtomValue(dashboardAtom);
  const quitWithoutSavedDashboard = useSetAtom(quitWithoutSavedDashboardAtom);

  const storeQuitWithoutSavedDashboard = (): void => {
    if (!isEditing) {
      return;
    }
    localStorage.setItem(
      'centreon-quit-without-saved-dashboard',
      JSON.stringify({
        ...dashboard,
        date: new Date().toISOString(),
        layout
      })
    );
  };

  useEffect(() => {
    quitWithoutSavedDashboard(null);
    window.addEventListener('beforeunload', storeQuitWithoutSavedDashboard);

    return () => {
      window.removeEventListener(
        'beforeunload',
        storeQuitWithoutSavedDashboard
      );
    };
  }, [isEditing, layout]);

  return {
    blockNavigation: blocker.reset,
    blocked: equals(blocker.state, 'blocked'),
    proceedNavigation: blocker.proceed
  };
};

export default useDashboardSaveBlocker;
