import { useAtomValue, useSetAtom } from 'jotai';
import {
  getPanelOptionsAndDataDerivedAtom,
  setPanelOptionsAndDataDerivedAtom
} from '../../atoms';

const useRefreshWebPageWidget = (id: string) => {
  const getPanelOptionsAndData = useAtomValue(
    getPanelOptionsAndDataDerivedAtom
  );

  const setPanelOptions = useSetAtom(setPanelOptionsAndDataDerivedAtom);

  const options = getPanelOptionsAndData(id)?.options;

  const refreshWebpageWidget = () => {
    const url = options?.url;

    setPanelOptions({
      id,
      options: { ...options, url: `${url} ` }
    });

    setTimeout(() => {
      setPanelOptions({
        id,
        options: { ...options, url }
      });
    }, 5);
  };

  return refreshWebpageWidget;
};

export default useRefreshWebPageWidget;
