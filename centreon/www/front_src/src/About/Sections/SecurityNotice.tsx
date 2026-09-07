import ShieldOutlinedIcon from '@mui/icons-material/ShieldOutlined';

import type { ReactElement } from 'react';
import { Trans } from 'react-i18next';

import { labelSecurityNotice } from '../translatedLabels';

const securityPolicyUrl =
  'https://github.com/centreon/centreon/security/policy';

const SecurityNotice = (): ReactElement => {
  return (
    <div className="flex items-start gap-3">
      <ShieldOutlinedIcon className="mt-1 shrink-0 text-xl text-success-main" />
      <p className="text-sm">
        <Trans
          components={{
            policy: (
              // biome-ignore lint/a11y/useAnchorContent: Trans fills in the link text
              <a
                className="text-primary-main hover:underline"
                href={securityPolicyUrl}
                rel="noreferrer noopener"
                target="_blank"
              />
            )
          }}
          defaults={labelSecurityNotice}
        />
      </p>
    </div>
  );
};

export default SecurityNotice;
