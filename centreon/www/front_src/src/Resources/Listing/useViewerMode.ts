import { useTransition } from 'react';

import { useAtom } from 'jotai';
import { equals } from 'ramda';

import { userAtom, ListingVariant } from '@centreon/ui-context';

interface ViewerMode {
  isPending: boolean;
  updateUser: () => void;
  viewerMode: ListingVariant;
}

const useViewerMode = (): ViewerMode => {
  const [user, setUser] = useAtom(userAtom);
  const [isPending, startTransition] = useTransition();

  const viewerMode = equals(user.user_interface_density, ListingVariant.compact)
    ? ListingVariant.extended
    : ListingVariant.compact;

  const updateUser = (): void =>
    startTransition(() => {
      setUser({
        ...user,
        user_interface_density: viewerMode
      });
    });

  return { isPending, updateUser, viewerMode };
};

export default useViewerMode;
