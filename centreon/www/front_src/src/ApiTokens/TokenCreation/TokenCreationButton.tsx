import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import { SaveButton as Button } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { labelCreateNewToken } from '../translatedLabels';

import TokenCreationDialog from './TokenCreationDialog';

const TokenCreationButton = (): JSX.Element => {
  const { t } = useTranslation();

  const [isCreatingToken, setIsCreatingToken] = useState(false);

  const { isAdmin } = useAtomValue(userAtom);

  const createToken = (): void => {
    setIsCreatingToken(true);
  };

  const closeDialog = (): void => {
    setIsCreatingToken(false);
  };

  return (
    <>
      <Button
        data-testid={labelCreateNewToken}
        disabled={!isAdmin}
        labelSave={t(labelCreateNewToken)}
        startIcon={false}
        onClick={createToken}
      />
      {isCreatingToken && (
        <TokenCreationDialog
          closeDialog={closeDialog}
          isDialogOpened={isCreatingToken}
        />
      )}
    </>
  );
};

export default TokenCreationButton;
