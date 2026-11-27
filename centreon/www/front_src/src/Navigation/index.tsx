import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useLocation, useNavigate } from 'react-router';

import { NavigationMenu } from '../components/NavigationMenu';
import { adaptNavigation } from './adaptNavigation';
import { getSelectedRoute } from './getSelectedRoute';
import Logo from './Logo';
import navigationAtom, {
  isNavigationPopoverOpenAtom,
  isSidebarOpenAtom
} from './navigationAtoms';
import {
  labelCollapseMenu,
  labelExpandMenu,
  labelMainNavigation
} from './translatedLabels';

const homeRoute = '/';

const Navigation = (): JSX.Element => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { pathname, search } = useLocation();

  const navigation = useAtomValue(navigationAtom);
  const [isOpen, setIsOpen] = useAtom(isSidebarOpenAtom);
  const setIsPopoverOpen = useSetAtom(isNavigationPopoverOpenAtom);

  const routes = useMemo(
    () => adaptNavigation(navigation?.result ?? [], t),
    [navigation, t]
  );

  const selectedRoute = useMemo(
    () => getSelectedRoute({ pathname, routes, search }),
    [pathname, routes, search]
  );

  const labels = useMemo(
    () => ({
      collapse: t(labelCollapseMenu),
      expand: t(labelExpandMenu),
      navigation: t(labelMainNavigation)
    }),
    [t]
  );

  return (
    <NavigationMenu
      header={
        <div className="flex h-12 shrink-0 items-center">
          <Logo isMiniLogo={!isOpen} onClick={() => navigate(homeRoute)} />
        </div>
      }
      isOpen={isOpen}
      LinkComponent={Link}
      labels={labels}
      onNavigate={navigate}
      onPopoverOpenChange={setIsPopoverOpen}
      onToggle={() => setIsOpen((currentIsOpen) => !currentIsOpen)}
      routes={routes}
      selectedRoute={selectedRoute}
    />
  );
};

export default Navigation;
