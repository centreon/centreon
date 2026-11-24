import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useCallback } from 'react';
import { useNavigate } from 'react-router';

import {
  formStateAtom,
  isCloseConfirmationDialogOpenAtom,
  isFormDirtyAtom
} from '../ConfigurationBase/atoms';
import { getServicesOfHostPath, servicesPagePath } from '../Services/utils';
import { useIsPageAllowed } from '../useIsPageAllowed';
import { getHostServicesUrl } from './utils';

export const useGoToServices = (): ((hostName: string) => void) => {
  const navigate = useNavigate();
  const isServicesPageAllowed = useIsPageAllowed(servicesPagePath);

  const [formState, setFormState] = useAtom(formStateAtom);
  const isFormDirty = useAtomValue(isFormDirtyAtom);
  const setIsCloseConfirmationDialogOpen = useSetAtom(
    isCloseConfirmationDialogOpenAtom
  );

  return useCallback(
    (hostName: string): void => {
      if (formState.isOpen && isFormDirty) {
        setIsCloseConfirmationDialogOpen(true);

        return;
      }

      if (isServicesPageAllowed) {
        // Shared by every page: an open host form would open on Services.
        setFormState((state) => ({ ...state, isOpen: false }));
        navigate(getServicesOfHostPath(hostName));

        return;
      }

      window.open(getHostServicesUrl(hostName), '_self');
    },
    [
      formState.isOpen,
      isFormDirty,
      isServicesPageAllowed,
      navigate,
      setFormState,
      setIsCloseConfirmationDialogOpen
    ]
  );
};
