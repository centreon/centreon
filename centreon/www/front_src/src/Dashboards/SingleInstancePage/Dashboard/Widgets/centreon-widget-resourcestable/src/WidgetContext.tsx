import type { PlatformVersions } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { type ReactElement, type ReactNode, useEffect, useMemo } from 'react';

import {
  isOnPublicPageLocalAtom,
  openTicketContextAtom,
  platformLocalAtom
} from './atom';
import type { OpenTicketContext } from './models';

interface WidgetProviderProps {
  children: ReactNode;
  isOnPublicPage: boolean;
  openTicketContext: OpenTicketContext;
  platform: PlatformVersions | null;
}

/**
 * Provides isolated Jotai state for each widget instance.
 *
 * This pattern solves the problem of multiple instances of the same widget
 * sharing state through Jotai atoms. By creating a new store per widget instance,
 * each widget gets its own isolated state.
 *
 * Global values (isOnPublicPage, platform, openTicketContext) are initialized
 * in the scoped store, allowing all components to use a consistent Jotai pattern
 * with useAtomValue() instead of mixing React Context and Jotai.
 */
export const WidgetProvider = ({
  children,
  isOnPublicPage,
  openTicketContext,
  platform
}: WidgetProviderProps): ReactElement => {
  const store = useMemo(() => {
    const s = createStore();
    s.set(isOnPublicPageLocalAtom, isOnPublicPage);
    s.set(platformLocalAtom, platform);
    s.set(openTicketContextAtom, openTicketContext);

    return s;
  }, []);

  // Sync global values when they change
  useEffect(() => {
    store.set(isOnPublicPageLocalAtom, isOnPublicPage);
  }, [isOnPublicPage, store]);

  useEffect(() => {
    store.set(platformLocalAtom, platform);
  }, [platform, store]);

  useEffect(() => {
    store.set(openTicketContextAtom, openTicketContext);
  }, [openTicketContext, store]);

  return <Provider store={store}>{children}</Provider>;
};
