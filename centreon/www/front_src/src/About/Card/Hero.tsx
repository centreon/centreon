import { useTranslation } from 'react-i18next';

import centreonLogoWhite from '../../assets/centreon-logo-white.svg';
import {
  labelCentreonLogo,
  labelInfraMonitoring,
  labelOpenSourceEdition,
  labelTagline
} from '../translatedLabels';

const heroGradient =
  'linear-gradient(100deg, #1f8fd6 0%, #6a4fd6 52%, #27a567 100%)';
const sheen =
  'radial-gradient(120% 120% at 0% 0%, rgba(255, 255, 255, 0.06), rgba(0, 0, 0, 0) 50%)';

interface Props {
  version?: string;
}

const Hero = ({ version }: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <div className="relative overflow-hidden bg-primary-main px-6 py-7 sm:px-10 sm:py-9">
      <div
        aria-hidden
        className="pointer-events-none absolute -right-20 -top-28 h-[360px] w-[360px] rounded-full opacity-[0.55] blur-3xl"
        style={{ backgroundImage: heroGradient }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0"
        style={{ backgroundImage: sheen }}
      />
      <div className="relative flex flex-col gap-4">
        <div className="flex items-center gap-3">
          <img
            alt={t(labelCentreonLogo)}
            className="h-7"
            src={centreonLogoWhite}
          />
          <span className="border-l border-white/30 pl-3 text-xs font-semibold uppercase tracking-wide text-white/90">
            {t(labelInfraMonitoring)}
          </span>
        </div>
        <div className="flex flex-wrap items-end gap-4">
          {version && (
            <span className="text-[34px] font-bold leading-none tracking-tight text-white">
              {version}
            </span>
          )}
          <span className="bg-white/15 px-2 py-1 text-xs font-medium text-white">
            {t(labelOpenSourceEdition)}
          </span>
        </div>
        <p className="max-w-[560px] text-[15px] leading-relaxed text-white/80">
          {t(labelTagline)}
        </p>
      </div>
    </div>
  );
};

export default Hero;
