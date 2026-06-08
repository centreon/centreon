import { useLocaleDateTimeFormat } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import {
  labelCentreon,
  labelCopyright,
  labelMadeWithCare
} from '../translatedLabels';

const AboutFooter = (): JSX.Element => {
  const { t } = useTranslation();
  const { format } = useLocaleDateTimeFormat();

  const year = format({
    date: new Date(),
    formatString: 'YYYY'
  });

  return (
    <div className="flex flex-col gap-1 border-t border-divider pt-4 text-xs text-text-secondary sm:flex-row sm:items-center sm:justify-between">
      <span>
        {t(labelCopyright)} © 2005 – {year} {t(labelCentreon)}
      </span>
      <span>{t(labelMadeWithCare)}</span>
    </div>
  );
};

export default AboutFooter;
