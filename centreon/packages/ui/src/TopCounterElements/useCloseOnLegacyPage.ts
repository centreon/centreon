import { Dispatch, SetStateAction, useEffect } from 'react';

import { useLocation } from 'react-router-dom';

interface Props {
  setToggled: Dispatch<SetStateAction<boolean>>;
}
const useCloseOnLegacyPage = ({ setToggled }: Props): void => {
  const { pathname, search } = useLocation();
  const isLegacyRoute = pathname.includes('main.php');

  const closeSubMenu = (): void => {
    setToggled(false);
  };

  useEffect(() => {
    if (!isLegacyRoute) {
      return () => undefined;
    }

    const iframe = document.getElementById('main-content') as HTMLIFrameElement;

    const closeSubMenuOnLegacyPage = (): void => {
      iframe?.contentWindow?.document?.addEventListener('click', closeSubMenu);
    };

    iframe?.addEventListener('load', closeSubMenuOnLegacyPage);

    return () => {
      iframe?.removeEventListener('load', closeSubMenuOnLegacyPage);
      iframe?.contentWindow?.document?.removeEventListener(
        'click',
        closeSubMenu
      );
    };
  }, [pathname, search]);
};

export default useCloseOnLegacyPage;
