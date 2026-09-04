import RocketLaunchIcon from '@mui/icons-material/RocketLaunch';

import type { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelEditionsUpsellDescription,
  labelScalingBeyondOpenSource,
  labelStartFreeTrial
} from '../translatedLabels';

const freeTrialUrl = 'https://www.centreon.com/free-trial/';

const EditionsUpsell = (): ReactElement => {
  const { t } = useTranslation();

  return (
    <div className="relative mt-4 flex flex-col items-start gap-4 overflow-hidden rounded-lg bg-brand-navy p-5 sm:flex-row sm:items-center">
      <div className="absolute -top-[60px] -right-[30px] h-[200px] w-[200px] rounded-full bg-[linear-gradient(100deg,#1F8FD6_0%,#6A4FD6_52%,#27A567_100%)] opacity-40 blur-[34px]" />
      <RocketLaunchIcon className="relative shrink-0 text-xl text-white" />
      <p className="relative flex-1 text-sm text-white">
        <strong className="font-medium">
          {t(labelScalingBeyondOpenSource)}
        </strong>{' '}
        {t(labelEditionsUpsellDescription)}
      </p>
      <a
        className="relative shrink-0 rounded bg-brand-navy-action px-4 py-2 font-medium text-brand-navy-action-text no-underline"
        href={freeTrialUrl}
        rel="noreferrer noopener"
        target="_blank"
      >
        {t(labelStartFreeTrial)}
      </a>
    </div>
  );
};

export default EditionsUpsell;
