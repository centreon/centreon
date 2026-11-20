// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import {
  type Acknowledgement,
  type Acl,
  acknowledgementAtom,
  type Downtime,
  downtimeAtom,
  type PlatformVersions,
  type User,
  userAtom
} from '@centreon/ui-context';

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
  acknowledgement: Acknowledgement;
  acl: Acl;
  children: ReactNode;
  downtime: Downtime;
  isOnPublicPage: boolean;
  openTicketContext: OpenTicketContext;
  platform: PlatformVersions | null;
  user: User;
}

export const WidgetProvider = ({
  acknowledgement,
  acl,
  children,
  downtime,
  isOnPublicPage,
  openTicketContext,
  platform,
  user
}: WidgetProviderProps): ReactElement => {
  const store = useMemo(() => {
    const newStore = createStore();
    newStore.set(aclLocalAtom, acl);
    newStore.set(isOnPublicPageLocalAtom, isOnPublicPage);
    newStore.set(platformLocalAtom, platform);
    newStore.set(acknowledgementAtom, acknowledgement);
    newStore.set(downtimeAtom, downtime);
    newStore.set(userAtom, user);
    newStore.set(openTicketContextAtom, openTicketContext);

    return newStore;
  }, [
    acl,
    isOnPublicPage,
    openTicketContext,
    platform,
    acknowledgement,
    downtime,
    user
  ]);

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
    store.set(acknowledgementAtom, acknowledgement);
  }, [acknowledgement, store]);

  useEffect(() => {
    store.set(downtimeAtom, downtime);
  }, [downtime, store]);

  useEffect(() => {
    store.set(userAtom, user);
  }, [user, store]);

  useEffect(() => {
    store.set(openTicketContextAtom, (prev) =>
      equals(prev, openTicketContext) ? prev : openTicketContext
    );
  }, [openTicketContext, store]);

  return <Provider store={store}>{children}</Provider>;
};
