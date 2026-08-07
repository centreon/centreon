import ShieldOutlinedIcon from '@mui/icons-material/ShieldOutlined';

import { useTranslation } from 'react-i18next';

import {
  labelManyThanksToAllContributorsToTheSecurity,
  labelReportAVulnerability
} from '../translatedLabels';

const securityPolicyUrl =
  'https://github.com/centreon/centreon/security/policy';

const SecurityNotice = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <div className="flex items-start gap-2.5">
      <ShieldOutlinedIcon className="mt-0.5 shrink-0 text-xl text-success-main" />
      <p className="text-sm">
        {t(labelManyThanksToAllContributorsToTheSecurity)}{' '}
        <a
          className="text-primary-main hover:underline"
          href={securityPolicyUrl}
          rel="noreferrer noopener"
          target="_blank"
        >
          {t(labelReportAVulnerability)} ↗
        </a>
      </p>
    </div>
  );
};

export default SecurityNotice;
