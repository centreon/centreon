import GitHubIcon from '@mui/icons-material/GitHub';
import StarBorderIcon from '@mui/icons-material/StarBorder';

import { CentreonLogo } from '@centreon/ui';

import type { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelCentreon,
  labelOpenSourceEdition,
  labelPlatformTagline,
  labelStarCentreonOnGithub,
  labelStarOnGithub
} from './translatedLabels';

const githubUrl = 'https://github.com/centreon/centreon';

interface Props {
  showOpenSourceEditionTag: boolean;
  version?: string;
}

const Hero = ({ version, showOpenSourceEditionTag }: Props): ReactElement => {
  const { t } = useTranslation();

  return (
    <div className="relative overflow-hidden bg-brand-navy p-8">
      <div className="absolute -top-[120px] -right-20 h-[360px] w-[360px] rounded-full bg-[linear-gradient(100deg,#1F8FD6_0%,#6A4FD6_52%,#27A567_100%)] opacity-55 blur-2xl" />
      <div className="relative flex flex-col items-start gap-6 sm:flex-row sm:justify-between">
        <div className="w-[168px] brightness-0 invert">
          <CentreonLogo />
        </div>
        <a
          aria-label={t(labelStarCentreonOnGithub)}
          className="inline-flex shrink-0 items-center gap-1 rounded bg-white/12 px-3 py-2 text-sm whitespace-nowrap text-white no-underline transition-colors hover:bg-white/22"
          href={githubUrl}
          rel="noreferrer"
          target="_blank"
          title={t(labelStarCentreonOnGithub)}
        >
          <GitHubIcon className="text-base" />
          <StarBorderIcon className="text-base" />
          {t(labelStarOnGithub)}
        </a>
      </div>
      <div className="relative mt-6 flex flex-wrap items-end gap-4">
        <p className="text-[34px] leading-none font-bold tracking-[-0.01em] text-white">
          {version || t(labelCentreon)}
        </p>
        {showOpenSourceEditionTag && (
          <span className="mb-1 rounded-full bg-white/14 px-3 py-2 text-xs font-medium text-white">
            {t(labelOpenSourceEdition)}
          </span>
        )}
      </div>
      <p className="relative mt-3 max-w-[560px] text-sm text-white/78">
        {t(labelPlatformTagline)}
      </p>
    </div>
  );
};

export default Hero;
