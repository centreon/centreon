import { DashboardGlobalRole, userAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

const useIsViewerUser = (): boolean => {
  const { dashboard } = useAtomValue(userAtom);

  const isViewer = equals(
    dashboard?.globalUserRole,
    DashboardGlobalRole.viewer
  );

  return isViewer;
};

export default useIsViewerUser;
