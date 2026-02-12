import type { Acl, PlatformVersions } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { equals } from 'ramda';
import { type ReactElement, type ReactNode, useEffect, useMemo } from 'react';

import {
  aclLocalAtom,
  isOnPublicPageLocalAtom,
  openTicketContextAtom,
  platformLocalAtom
} from './atom';
import type { OpenTicketContext } from './models';

interface WidgetProviderProps {
  acl: Acl;
  children: ReactNode;
  isOnPublicPage: boolean;
  openTicketContext: OpenTicketContext;
  platform: PlatformVersions | null;
}

export const WidgetProvider = ({
  acl,
  children,
  isOnPublicPage,
  openTicketContext,
  platform
}: WidgetProviderProps): ReactElement => {
  console.log('WidgetProvider render');
  const store = useMemo(() => {
    const newStore = createStore();
    newStore.set(aclLocalAtom, acl);
    newStore.set(isOnPublicPageLocalAtom, isOnPublicPage);
    newStore.set(platformLocalAtom, platform);
    newStore.set(openTicketContextAtom, openTicketContext);

    return newStore;
  }, [acl, isOnPublicPage, openTicketContext, platform]);

  useEffect(() => {
    store.set(aclLocalAtom, acl);
  }, [acl, store]);

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
