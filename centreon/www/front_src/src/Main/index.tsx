import { Suspense, startTransition, useEffect } from 'react';

import 'dayjs/locale/en';
import 'dayjs/locale/pt';
import 'dayjs/locale/fr';
import 'dayjs/locale/es';
import 'dayjs/locale/de';
import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import isBetween from 'dayjs/plugin/isBetween';
import isSameOrBefore from 'dayjs/plugin/isSameOrBefore';
import isToday from 'dayjs/plugin/isToday';
import isYesterday from 'dayjs/plugin/isYesterday';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import weekday from 'dayjs/plugin/weekday';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { and, equals, isNil, not } from 'ramda';
import { Outlet, useLocation } from 'react-router-dom';

import { isOnPublicPageAtom } from '@centreon/ui-context';

import reactRoutes from '../reactRoutes/routeMap';

import { MainLoaderWithoutTranslation } from './MainLoader';
import { platformInstallationStatusAtom } from './atoms/platformInstallationStatusAtom';
import useMain, { router } from './useMain';
import { areUserParametersLoadedAtom } from './useUser';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(isToday);
dayjs.extend(isYesterday);
dayjs.extend(weekday);
dayjs.extend(isBetween);
dayjs.extend(isSameOrBefore);
dayjs.extend(duration);

const Main = (): JSX.Element => {
  const navigate = router.useNavigate();
  const { pathname } = useLocation();

  const hasReachedAPublicPage = !!pathname.match(/^\/public\//);

  useMain(hasReachedAPublicPage);

  const [areUserParametersLoaded] = useAtom(areUserParametersLoadedAtom);
  const platformInstallationStatus = useAtomValue(
    platformInstallationStatusAtom
  );
  const setIsOnPublicPageAtom = useSetAtom(isOnPublicPageAtom);

  const navigateTo = (path: string): string => {
    navigate(path);
    window.location.reload();
  };

  console.log('hello darkness my old friend');

  startTransition(() => {
    setIsOnPublicPageAtom(hasReachedAPublicPage);
  });

  useEffect(() => {
    if (isNil(platformInstallationStatus) || isNil(areUserParametersLoaded)) {
      return;
    }

    if (not(platformInstallationStatus.isInstalled)) {
      navigateTo(reactRoutes.install);

      return;
    }

    const canUpgrade = and(
      platformInstallationStatus.hasUpgradeAvailable,
      not(areUserParametersLoaded)
    );

    if (canUpgrade) {
      navigateTo(reactRoutes.upgrade);

      return;
    }

    if (
      not(areUserParametersLoaded) &&
      !equals(pathname, reactRoutes.authenticationDenied) &&
      !equals(pathname, reactRoutes.logout) &&
      !equals(pathname, reactRoutes.login)
    ) {
      navigate(reactRoutes.login);
    }
  }, [platformInstallationStatus, areUserParametersLoaded]);

  if (!hasReachedAPublicPage && isNil(platformInstallationStatus)) {
    return <MainLoaderWithoutTranslation />;
  }

  return (
    <Suspense fallback={<MainLoaderWithoutTranslation />}>
      <Outlet />
    </Suspense>
  );
};

export default Main;
