import { FallbackPage } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import {
  labelLostInSpace,
  labelYouAreNotAllowedToSeeThisPage
} from './translatedLabels';

const NotAllowedPage = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <FallbackPage
      message={t(labelYouAreNotAllowedToSeeThisPage)}
      title={t(labelLostInSpace)}
    />
  );
};

export default NotAllowedPage;
