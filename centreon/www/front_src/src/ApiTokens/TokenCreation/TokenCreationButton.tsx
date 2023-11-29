import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { SaveButton as Button } from '@centreon/ui';

import { labelCreateNewToken } from '../translatedLabels';

import { isCreateTokenAtom } from './atoms';

const TokenCreationButton = (): JSX.Element => {
  const { t } = useTranslation();
  const setIsCreateToken = useSetAtom(isCreateTokenAtom);

  const createToken = (): void => {
    setIsCreateToken(true);
  };

  return (
    <Button
      labelProps={{ variant: 'body2' }}
      labelSave={t(labelCreateNewToken)}
      startIcon={false}
      onClick={createToken}
    />
  );
};

export default TokenCreationButton;
