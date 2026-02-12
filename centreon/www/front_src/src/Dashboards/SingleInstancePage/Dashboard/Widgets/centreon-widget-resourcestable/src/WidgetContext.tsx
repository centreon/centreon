import type { PlatformVersions } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { equals } from 'ramda';
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

export const WidgetProvider = ({
  children,
  isOnPublicPage,
  openTicketContext,
  platform
}: WidgetProviderProps): ReactElement => {
  const store = useMemo(() => {
    const newStore = createStore();
    newStore.set(isOnPublicPageLocalAtom, isOnPublicPage);
    newStore.set(platformLocalAtom, platform);
    newStore.set(openTicketContextAtom, openTicketContext);

    return newStore;
  }, [isOnPublicPage, openTicketContext, platform]);

  useEffect(() => {
    store.set(isOnPublicPageLocalAtom, isOnPublicPage);
  }, [isOnPublicPage, store]);

  useEffect(() => {
    store.set(platformLocalAtom, platform);
  }, [platform, store]);

  useEffect(() => {
    store.set(openTicketContextAtom, (prev) =>
      equals(prev, openTicketContext) ? prev : openTicketContext
    );
  }, [openTicketContext, store]);

  return <Provider store={store}>{children}</Provider>;
};
