import GitHubIcon from '@mui/icons-material/GitHub';
import HelpOutlineIcon from '@mui/icons-material/HelpOutline';
import RocketLaunchIcon from '@mui/icons-material/RocketLaunch';

import type { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import TheWatchIcon from '../Icons/TheWatchIcon';
import {
  labelBrowseTheDocs,
  labelCompareEditions,
  labelContributeOnGithub,
  labelContributeOnGithubDescription,
  labelDocumentationAndGuides,
  labelDocumentationAndGuidesDescription,
  labelEditionsAndCloud,
  labelEditionsAndCloudDescription,
  labelGetMoreFromCentreon,
  labelJoinTheWatch,
  labelOpenTheRepository,
  labelTheWatchCommunity,
  labelTheWatchCommunityDescription
} from '../translatedLabels';
import ResourceCard from './ResourceCard';

const links = {
  docs: 'https://docs.centreon.com',
  editions: 'https://www.centreon.com/pricing-centreon-infra-monitoring/',
  github: 'https://github.com/centreon/centreon',
  watch: 'https://thewatch.centreon.com'
};

export const pendoSlotId = 'about-pendo-slot';

const ResourcesGrid = (): ReactElement => {
  const { t } = useTranslation();

  return (
    <div className="border-t border-divider pt-3" id="about-resources">
      <p className="mb-2 font-medium text-section-title">
        {t(labelGetMoreFromCentreon)}
      </p>
      <div className="flex flex-col gap-2 lg:flex-row">
        <div className="@container min-w-0 flex-1">
          <div
            className="grid grid-cols-1 gap-2 @xl:grid-cols-2"
            id="about-resources-grid"
          >
            <ResourceCard
              actionLabel={labelBrowseTheDocs}
              description={labelDocumentationAndGuidesDescription}
              href={links.docs}
              Icon={HelpOutlineIcon}
              id="about-resource-documentation"
              title={labelDocumentationAndGuides}
            />
            <ResourceCard
              actionLabel={labelJoinTheWatch}
              description={labelTheWatchCommunityDescription}
              href={links.watch}
              Icon={TheWatchIcon}
              id="about-resource-the-watch"
              title={labelTheWatchCommunity}
            />
            <ResourceCard
              actionLabel={labelOpenTheRepository}
              description={labelContributeOnGithubDescription}
              href={links.github}
              Icon={GitHubIcon}
              id="about-resource-github"
              title={labelContributeOnGithub}
              tone="navy"
            />
            <ResourceCard
              actionLabel={labelCompareEditions}
              description={labelEditionsAndCloudDescription}
              href={links.editions}
              Icon={RocketLaunchIcon}
              id="about-resource-editions"
              title={labelEditionsAndCloud}
              tone="navy"
            />
          </div>
        </div>
        {/* Filled by Pendo at runtime. It must stay childless: React would
            wipe any content injected into an element it renders children in.
            Hidden while empty, so the page is unchanged without Pendo. */}
        <div className="empty:hidden lg:w-1/2 lg:shrink-0" id={pendoSlotId} />
      </div>
    </div>
  );
};

export default ResourcesGrid;
