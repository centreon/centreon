import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import routeMap from '../../reactRoutes/routeMap';
import { isSidebarOpenAtom } from '../navigationAtoms';
import {
  labelCentreonLogo,
  labelInfrastructureMonitoring
} from '../translatedLabels';
import logoCentreon from './icons/logo-centreon.svg';
import { NavIcon } from './NavIcon';
import { brandColor, widthCollapsed, wordmarkColor } from './tokens';

const labelCentreon = 'centreon';

// Fixed 48px brand block; the glyph keeps the same X position in both modes
// (centered on the 44px icon column), the wordmark only shows when expanded.
export const MenuLogo = (): JSX.Element => {
  const { t } = useTranslation();
  const isSidebarOpen = useAtomValue(isSidebarOpenAtom);

  return (
    <Link
      aria-label={t(labelCentreonLogo)}
      className={`flex h-12 shrink-0 items-center gap-2 no-underline ${isSidebarOpen ? '' : widthCollapsed}`}
      data-testid={labelCentreonLogo}
      title={t(labelCentreonLogo)}
      to={routeMap.about}
    >
      <NavIcon
        className="ml-[7px] h-[35px] w-[30px]"
        colorClassName={brandColor}
        src={logoCentreon as string}
      />
      {isSidebarOpen && (
        <div className={`whitespace-nowrap ${wordmarkColor}`}>
          <div className="font-['Space_Grotesk',system-ui,sans-serif] font-bold text-[20px] leading-none tracking-[-0.01em]">
            {labelCentreon}
          </div>
          <div className="mt-[2px] font-['Red_Hat_Display',system-ui,sans-serif] font-bold text-[12px] leading-[1.2]">
            {t(labelInfrastructureMonitoring)}
          </div>
        </div>
      )}
    </Link>
  );
};
