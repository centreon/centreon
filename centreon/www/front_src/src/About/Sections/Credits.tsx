import type { ReactElement } from 'react';
import { Trans } from 'react-i18next';

import { labelContributorsNotice } from '../translatedLabels';

const contributorsGraphUrl =
  'https://github.com/centreon/centreon/graphs/contributors';

const Credits = (): ReactElement => {
  return (
    <div>
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
