import { Suspense, lazy } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtomValue, useSetAtom } from 'jotai';

import {
  TiledListingPage,
  Header,
  SimpleDialog,
  DashboardForm
} from '@centreon/ui';

import {
  labelCancel,
  labelCreate,
  labelCreateDashboard,
  labelDashboards,
  labelDescription,
  labelName,
  labelUpdate,
  labelUpdateDashboard
} from './translatedLabels';
import ListingSkeleton from './Skeleton';
import {
  closeDialogAtom,
  isDialogOpenAtom,
  selectedDashboardAtom
} from './atoms';
import useSubmitDashboard from './useSubmitDashboard';

const Listing = lazy(() => import('./Listing'));

const formLabels = {
  actions: {
    cancel: labelCancel,
    submit: {
      create: labelCreate,
      update: labelUpdate
    }
  },
  entity: {
    description: labelDescription,
    name: labelName
  },
  title: {
    create: labelCreateDashboard,
    update: labelUpdateDashboard
  }
};

const Dashboards = (): JSX.Element => {
  const { t } = useTranslation();

  const isDialogOpen = useAtomValue(isDialogOpenAtom);
  const selectedDashboard = useAtomValue(selectedDashboardAtom);
  const closeDialog = useSetAtom(closeDialogAtom);

  const { submit } = useSubmitDashboard();

  return (
    <TiledListingPage>
      <Header title={t(labelDashboards)} />
      <Suspense fallback={<ListingSkeleton />}>
        <Listing />
      </Suspense>
      <SimpleDialog open={isDialogOpen} onClose={closeDialog}>
        <DashboardForm
          labels={formLabels}
          resource={selectedDashboard?.dashboard || undefined}
          variant={selectedDashboard?.variant}
          onCancel={closeDialog}
          onSubmit={submit}
        />
      </SimpleDialog>
    </TiledListingPage>
  );
};

export default Dashboards;
