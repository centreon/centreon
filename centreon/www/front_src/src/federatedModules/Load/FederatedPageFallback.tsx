import { FallbackPage } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { labelCannotLoadModule } from '../translatedLabels';

const FederatedPageFallback = (): JSX.Element => {
  const { t } = useTranslation();

  return <FallbackPage message="" title={t(labelCannotLoadModule)} />;
};

export default FederatedPageFallback;
