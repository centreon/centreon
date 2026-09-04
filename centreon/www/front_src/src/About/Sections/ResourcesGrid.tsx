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

const ResourcesGrid = (): ReactElement => {
  const { t } = useTranslation();

  return (
    <div className="border-t border-divider pt-3">
      <p className="mb-2 font-medium text-section-title">
        {t(labelGetMoreFromCentreon)}
      </p>
      <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
        <ResourceCard
          actionLabel={labelBrowseTheDocs}
          description={labelDocumentationAndGuidesDescription}
          href={links.docs}
          Icon={HelpOutlineIcon}
          title={labelDocumentationAndGuides}
        />
        <ResourceCard
          actionLabel={labelJoinTheWatch}
          description={labelTheWatchCommunityDescription}
          href={links.watch}
          Icon={TheWatchIcon}
          title={labelTheWatchCommunity}
        />
        <ResourceCard
          actionLabel={labelOpenTheRepository}
          description={labelContributeOnGithubDescription}
          href={links.github}
          Icon={GitHubIcon}
          title={labelContributeOnGithub}
          tone="navy"
        />
        <ResourceCard
          actionLabel={labelCompareEditions}
          description={labelEditionsAndCloudDescription}
          href={links.editions}
          Icon={RocketLaunchIcon}
          title={labelEditionsAndCloud}
          tone="navy"
        />
      </div>
    </div>
  );
};

export default ResourcesGrid;
