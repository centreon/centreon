import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { SaveButton as Button } from '@centreon/ui';

import { labelCreateNewToken } from '../translatedLabels';

import { isCreatingTokenAtom } from './atoms';

const TokenCreationButton = (): JSX.Element => {
  const { t } = useTranslation();
  const setIsCreatingToken = useSetAtom(isCreatingTokenAtom);

  const createToken = (): void => {
    setIsCreatingToken(true);
  };

  return (
    <Button
      data-testid={labelCreateNewToken}
      labelSave={t(labelCreateNewToken)}
      startIcon={false}
      onClick={createToken}
    />
  );
};

export default TokenCreationButton;
