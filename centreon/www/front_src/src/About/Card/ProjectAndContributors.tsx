import { Fragment } from 'react';
import { useTranslation } from 'react-i18next';

import { projectLeaders } from '../data';
import { links } from '../links';
import {
  labelManyThanksToAllDevelopersAndContributors,
  labelProjectLeaders,
  labelSeeTheFullListOnGitHub
} from '../translatedLabels';
import ExternalLink from './ExternalLink';

const ProjectAndContributors = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-3">
      <div>
        <div className="text-sm font-medium text-text-primary">
          {t(labelProjectLeaders)}{' '}
          <span className="text-text-secondary">({projectLeaders.length})</span>
        </div>
        <div className="mt-1 flex flex-wrap items-center gap-2">
          {projectLeaders.map((name, index) => (
            <Fragment key={name}>
              {index > 0 && <span className="text-[#999999]">/</span>}
              <span>{name}</span>
            </Fragment>
          ))}
        </div>
      </div>
      <p>
        {t(labelManyThanksToAllDevelopersAndContributors)}{' '}
        <ExternalLink
          ariaLabel={labelSeeTheFullListOnGitHub}
          href={links.githubContributors}
        >
          {t(labelSeeTheFullListOnGitHub)}
        </ExternalLink>
      </p>
    </div>
  );
};

export default ProjectAndContributors;
