import { createStore, Provider } from 'jotai';
import {
  createContext,
  type ReactElement,
  type ReactNode,
  useContext,
  useMemo
} from 'react';

import type { OpenTicketContext } from './models';

interface Version {
  fix: string;
  major: string;
  minor: string;
  version: string;
}

interface PlatformVersions {
  modules: Record<string, Version>;
  web: Version;
  widgets: Record<string, Version | null>;
}

/**
 * Context for global values that need to be accessible inside the scoped Jotai Provider.
 * These values come from global Jotai atoms (ui-context) but must be passed via React Context
 * because the widget uses a scoped Jotai store for widget-specific state isolation.
 *
 * Also includes openTicketContext for deeply nested components (like column renderers)
 * that cannot receive props directly.
 */
interface WidgetGlobalContextValue {
  isOnPublicPage: boolean;
  openTicketContext: OpenTicketContext;
  platform: PlatformVersions | null;
}

const defaultOpenTicketContext: OpenTicketContext = {
  displayResources: 'withoutTicket',
  enableHostTicketCreation: false,
  enableServiceTicketCreation: false,
  isDownHostHidden: false,
  isOpenTicketEnabled: false,
  isOpenTicketInstalled: false,
  isUnreachableHostHidden: false,
  provider: undefined
};

const WidgetGlobalContext = createContext<WidgetGlobalContextValue>({
  isOnPublicPage: false,
  openTicketContext: defaultOpenTicketContext,
  platform: null
});

export const useWidgetGlobalContext = (): WidgetGlobalContextValue =>
  useContext(WidgetGlobalContext);

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
 * Global values (isOnPublicPage, platform) and openTicketContext are passed via
 * React Context since they need to be accessible by deeply nested components
 * that can't receive props directly (like column renderers).
 */
export const WidgetProvider = ({
  children,
  isOnPublicPage,
  openTicketContext,
  platform
}: WidgetProviderProps): ReactElement => {
  const store = useMemo(() => createStore(), []);

  const globalContextValue = useMemo(
    () => ({
      isOnPublicPage,
      openTicketContext,
      platform
    }),
    [isOnPublicPage, openTicketContext, platform]
  );

  return (
    <WidgetGlobalContext.Provider value={globalContextValue}>
      <Provider store={store}>{children}</Provider>
    </WidgetGlobalContext.Provider>
  );
};
