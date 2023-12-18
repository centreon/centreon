import { useTranslation } from 'react-i18next';

import { SaveButton as Button } from '@centreon/ui';

import { labelCreateNewToken } from '../translatedLabels';

const TokenCreationButton = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Button
      labelProps={{ variant: 'body2' }}
      labelSave={t(labelCreateNewToken)}
      startIcon={false}
    />
  );
};

export default TokenCreationButton;
