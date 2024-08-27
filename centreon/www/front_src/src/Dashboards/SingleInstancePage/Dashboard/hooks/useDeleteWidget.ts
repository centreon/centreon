import { useSetAtom } from 'jotai';

import { isEditingAtom, removePanelDerivedAtom } from '../atoms';

interface UseDeleteWidgetState {
  deleteWidget: (id: string) => () => void;
}

const useDeleteWidget = (): UseDeleteWidgetState => {
  const removePanel = useSetAtom(removePanelDerivedAtom);
  const setIsEditing = useSetAtom(isEditingAtom);

  const deleteWidget = (id: string) => (): void => {
    setIsEditing(() => true);
    removePanel(id);
  };

  return {
    deleteWidget
  };
};

export default useDeleteWidget;
