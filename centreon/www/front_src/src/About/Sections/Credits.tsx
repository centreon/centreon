import type { ReactElement } from 'react';
import { Trans } from 'react-i18next';

import {
  labelContributorsNotice,
  labelProjectLeadersWithCount
} from '../translatedLabels';

export const projectLeaders = ['Julien Mathis', 'Romain Le Merlus'];

const contributorsGraphUrl =
  'https://github.com/centreon/centreon/graphs/contributors';

const Credits = (): ReactElement => {
  return (
    <div>
      <p className="mb-2 font-bold text-text-primary">
        <Trans
          components={{
            count: <span className="font-normal text-text-secondary" />
          }}
          defaults={labelProjectLeadersWithCount}
          values={{ total: projectLeaders.length }}
        />
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
        <Trans
          components={{
            contributors: (
              // biome-ignore lint/a11y/useAnchorContent: Trans fills in the link text
              <a
                className="text-primary-main hover:underline"
                href={contributorsGraphUrl}
                rel="noreferrer noopener"
                target="_blank"
              />
            )
          }}
          defaults={labelContributorsNotice}
        />
      </p>
    </div>
  );
};

export default Credits;
