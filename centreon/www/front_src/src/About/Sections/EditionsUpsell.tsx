import RocketLaunchIcon from '@mui/icons-material/RocketLaunch';

import { useTranslation } from 'react-i18next';

import {
  labelEditionsUpsellDescription,
  labelScalingBeyondOpenSource,
  labelStartFreeTrial
} from '../translatedLabels';

const editionsUrl = 'https://www.centreon.com/editions/';

const EditionsUpsell = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <div className="relative mt-4.5 flex items-center gap-4 overflow-hidden rounded-lg bg-[#131D5A] p-5">
      <div className="absolute -top-[60px] -right-[30px] h-[200px] w-[200px] rounded-full bg-[linear-gradient(100deg,#1F8FD6_0%,#6A4FD6_52%,#27A567_100%)] opacity-40 blur-[34px]" />
      <RocketLaunchIcon className="relative shrink-0 text-xl text-white" />
      <p className="relative flex-1 text-sm text-white">
        <strong className="font-medium">
          {t(labelScalingBeyondOpenSource)}
        </strong>{' '}
        {t(labelEditionsUpsellDescription)}
      </p>
      <a
        className="relative shrink-0 rounded bg-primary-light px-4.5 py-2.5 font-medium whitespace-nowrap text-primary-main no-underline"
        href={editionsUrl}
        rel="noreferrer noopener"
        target="_blank"
      >
        {t(labelStartFreeTrial)}
      </a>
    </div>
  );
};

export default EditionsUpsell;
