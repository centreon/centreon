import AutoAwesomeOutlinedIcon from '@mui/icons-material/AutoAwesomeOutlined';

import { useTranslation } from 'react-i18next';

import { links } from '../links';
import {
  labelEditionsAddFeatures,
  labelScalingBeyondOpenSource,
  labelStartFreeTrial
} from '../translatedLabels';

const blob = 'linear-gradient(100deg, #1f8fd6 0%, #6a4fd6 52%, #27a567 100%)';

const UpsellBanner = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <div className="relative overflow-hidden bg-primary-main">
      <div
        aria-hidden
        className="pointer-events-none absolute -right-10 -top-16 h-[200px] w-[200px] rounded-full opacity-50 blur-3xl"
        style={{ backgroundImage: blob }}
      />
      <div className="relative flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-start gap-2.5">
          <AutoAwesomeOutlinedIcon
            className="mt-0.5 shrink-0 text-white"
            fontSize="small"
          />
          <p className="text-sm text-white">
            <b className="font-medium">{t(labelScalingBeyondOpenSource)}</b>{' '}
            {t(labelEditionsAddFeatures)}
          </p>
        </div>
        <a
          className="shrink-0 self-start bg-[#cde7fc] px-3 py-2 text-[13px] font-medium text-[#2e68aa] hover:bg-white sm:self-auto"
          href={links.freeTrial}
          rel="noreferrer noopener"
          target="_blank"
        >
          {t(labelStartFreeTrial)}
        </a>
      </div>
    </div>
  );
};

export default UpsellBanner;
