import { useMemo } from 'react';

import { useAtomValue } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from './atoms';

const useCanEditProperties = (): {
  canEdit?: boolean;
  canEditField?: boolean;
} => {
  const hasEditPermission = useAtomValue(hasEditPermissionAtom);
  const isEditing = useAtomValue(isEditingAtom);

  const canEdit = useMemo(() => hasEditPermission, [hasEditPermission]);

  return {
    canEdit,
    canEditField: canEdit && isEditing
  };
};

export const editProperties = { useCanEditProperties };
