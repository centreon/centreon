import { useAtomValue, useSetAtom } from "jotai"
import { unstable_useBlocker } from "react-router"
import { dashboardAtom, isEditingAtom, quitWithoutSavedDashboardAtom } from "./atoms"
import { equals } from "ramda";
import { useEffect } from "react";
import { NamedEntity } from "./models";

interface UseDashboardSaveBlockerState {
  blocked: boolean;
  proceedNavigation?: () => void;
  blockNavigation?: () => void;
}

const useDashboardSaveBlocker = (dashboard: Partial<NamedEntity>): UseDashboardSaveBlockerState => {
  const isEditing = useAtomValue(isEditingAtom);

  const blocker = unstable_useBlocker(isEditing);

  const { layout } = useAtomValue(dashboardAtom);
  const quitWithoutSavedDashboard = useSetAtom(quitWithoutSavedDashboardAtom);

  const storeQuitWithoutSavedDashboard = () => {
    if (!isEditing) {
      return;
    }
    localStorage.setItem('centreon-quit-without-saved-dashboard', JSON.stringify({
      ...dashboard,
      layout,
      date: new Date().toISOString()
    }));
  }

  useEffect(() => {
    quitWithoutSavedDashboard(null);
    window.addEventListener('beforeunload', storeQuitWithoutSavedDashboard);

    return () => {
      window.removeEventListener('beforeunload', storeQuitWithoutSavedDashboard);
    }
  }, [isEditing, layout]);

  return {
    blocked: equals(blocker.state, 'blocked'),
    proceedNavigation: blocker.proceed,
    blockNavigation: blocker.reset
  }
}

export default useDashboardSaveBlocker;