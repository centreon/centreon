import { useLocaleDateTimeFormat } from '@centreon/ui';

import type { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { labelMadeWithCare } from '../translatedLabels';

const Copyright = (): ReactElement => {
  const { t } = useTranslation();
  const { format } = useLocaleDateTimeFormat();

  const year = format({
    date: new Date(),
    formatString: 'YYYY'
  });

  return (
    <div className="mt-6 flex flex-wrap items-center justify-between gap-2 text-xs text-text-secondary">
      <p>Copyright © 2005 - {year} Centreon</p>
      <p>{t(labelMadeWithCare)}</p>
    </div>
  );
};

export default Copyright;
