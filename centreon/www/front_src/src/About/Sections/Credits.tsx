import type { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelManyThanksToAllDevelopers,
  labelProjectLeaders,
  labelSeeFullListOnGithub
} from '../translatedLabels';

export const projectLeaders = ['Julien Mathis', 'Romain Le Merlus'];

const contributorsGraphUrl =
  'https://github.com/centreon/centreon/graphs/contributors';

const Credits = (): ReactElement => {
  const { t } = useTranslation();

  return (
    <div>
      <p className="mb-2 font-bold text-text-primary">
        {t(labelProjectLeaders)}{' '}
        <span className="font-normal text-text-secondary">
          ({projectLeaders.length})
        </span>
      </p>
      <div className="mb-2 flex flex-wrap items-center gap-2 text-text-secondary">
        {projectLeaders.map((name, index) => (
          <span className="flex gap-2" key={name}>
            {index > 0 && <span className="text-text-disabled">/</span>}
            <span>{name}</span>
          </span>
        ))}
      </div>
      <p className="text-sm">
        {t(labelManyThanksToAllDevelopers)}{' '}
        <a
          className="text-primary-main hover:underline"
          href={contributorsGraphUrl}
          rel="noreferrer noopener"
          target="_blank"
        >
          {t(labelSeeFullListOnGithub)} ↗
        </a>
      </p>
    </div>
  );
};

export default Credits;
