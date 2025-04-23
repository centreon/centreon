import { Suspense, lazy } from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { isNil, not } from 'ramda';

import { userAtom } from '@centreon/ui-context';

import PageLoader from '../../components/PageLoader';
import { MainLoader } from '../MainLoader';
import { platformInstallationStatusAtom } from '../atoms/platformInstallationStatusAtom';
import { areUserParametersLoadedAtom } from '../useUser';

const App = lazy(() => import('../../App'));

const InitializationPage = (): JSX.Element => {
  const [areUserParametersLoaded] = useAtom(areUserParametersLoadedAtom);
  const user = useAtomValue(userAtom);
  const platformInstallationStatus = useAtomValue(
    platformInstallationStatusAtom
  );

  const canDisplayApp =
    not(isNil(platformInstallationStatus)) &&
    not(isNil(user)) &&
    areUserParametersLoaded;

  if (not(canDisplayApp)) {
    return <MainLoader />;
  }

  return (
    <Suspense fallback={<PageLoader />}>
      <App />
    </Suspense>
  );
};

export default InitializationPage;
