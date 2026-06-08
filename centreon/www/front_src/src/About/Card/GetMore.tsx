import ArrowForwardIcon from '@mui/icons-material/ArrowForward';
import AutoAwesomeOutlinedIcon from '@mui/icons-material/AutoAwesomeOutlined';
import ForumOutlinedIcon from '@mui/icons-material/ForumOutlined';
import GitHubIcon from '@mui/icons-material/GitHub';
import MenuBookOutlinedIcon from '@mui/icons-material/MenuBookOutlined';

import { useTranslation } from 'react-i18next';

import { links } from '../links';
import {
  labelBrowseTheDocs,
  labelCompareEditions,
  labelContributeDescription,
  labelContributeOnGitHub,
  labelDocumentationAndGuides,
  labelDocumentationDescription,
  labelEditionsAndCloud,
  labelEditionsDescription,
  labelGetMoreFromCentreon,
  labelJoinTheWatch,
  labelOpenTheRepository,
  labelTheWatchCommunity,
  labelTheWatchDescription
} from '../translatedLabels';

interface Card {
  cta: string;
  description: string;
  href: string;
  icon: JSX.Element;
  tile: 'light' | 'navy';
  title: string;
}

const cards: Array<Card> = [
  {
    cta: labelBrowseTheDocs,
    description: labelDocumentationDescription,
    href: links.documentation,
    icon: <MenuBookOutlinedIcon fontSize="small" />,
    tile: 'light',
    title: labelDocumentationAndGuides
  },
  {
    cta: labelJoinTheWatch,
    description: labelTheWatchDescription,
    href: links.theWatch,
    icon: <ForumOutlinedIcon fontSize="small" />,
    tile: 'light',
    title: labelTheWatchCommunity
  },
  {
    cta: labelOpenTheRepository,
    description: labelContributeDescription,
    href: links.github,
    icon: <GitHubIcon fontSize="small" />,
    tile: 'navy',
    title: labelContributeOnGitHub
  },
  {
    cta: labelCompareEditions,
    description: labelEditionsDescription,
    href: links.editions,
    icon: <AutoAwesomeOutlinedIcon fontSize="small" />,
    tile: 'navy',
    title: labelEditionsAndCloud
  }
];

const GetMore = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-4">
      <h2 className="text-base font-medium text-primary-main">
        {t(labelGetMoreFromCentreon)}
      </h2>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {cards.map(({ title, description, cta, href, icon, tile }) => (
          <a
            className="group flex items-start gap-3 border border-divider bg-background-paper p-3 transition-colors hover:border-primary-main"
            href={href}
            key={title}
            rel="noreferrer noopener"
            target="_blank"
          >
            <span
              className={`flex h-[38px] w-[38px] shrink-0 items-center justify-center ${
                tile === 'navy'
                  ? 'bg-primary-main text-white'
                  : 'bg-[#cde7fc] text-[#2e68aa]'
              }`}
            >
              {icon}
            </span>
            <div className="flex flex-col gap-1">
              <span className="text-sm font-medium text-text-primary">
                {t(title)}
              </span>
              <span className="text-xs text-text-secondary">
                {t(description)}
              </span>
              <span className="mt-1 inline-flex items-center gap-1 text-[13px] font-medium text-[#2e68aa]">
                {t(cta)}
                <ArrowForwardIcon
                  className="transition-transform group-hover:translate-x-0.5"
                  sx={{ fontSize: 13 }}
                />
              </span>
            </div>
          </a>
        ))}
      </div>
    </div>
  );
};

export default GetMore;
