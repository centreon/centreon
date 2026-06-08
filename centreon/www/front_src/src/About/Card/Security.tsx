import ShieldOutlinedIcon from '@mui/icons-material/ShieldOutlined';

import { useTranslation } from 'react-i18next';

import { links } from '../links';
import {
  labelReportAVulnerability,
  labelSecurityAcknowledgement
} from '../translatedLabels';
import ExternalLink from './ExternalLink';

const Security = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <div className="flex items-start gap-2.5">
      <ShieldOutlinedIcon
        className="mt-0.5 shrink-0 text-primary-main"
        fontSize="small"
      />
      <p>
        {t(labelSecurityAcknowledgement)}{' '}
        <ExternalLink
          ariaLabel={labelReportAVulnerability}
          href={links.reportVulnerability}
        >
          {t(labelReportAVulnerability)}
        </ExternalLink>
      </p>
    </div>
  );
};

export default Security;
