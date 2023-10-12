import { useSetAtom } from 'jotai';

import { removePanelDerivedAtom } from '../atoms';

interface UseDeleteWidgetState {
  deleteWidget: (id: string) => () => void;
}

const useDeleteWidget = (): UseDeleteWidgetState => {
  const removePanel = useSetAtom(removePanelDerivedAtom);

  const deleteWidget = (id: string) => (): void => {
    removePanel(id);
  };

  return {
    deleteWidget
  };
};

export default useDeleteWidget;
