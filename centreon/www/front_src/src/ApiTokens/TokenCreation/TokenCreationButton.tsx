import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import { SaveButton as Button } from '@centreon/ui';

import { labelCreateNewToken } from '../translatedLabels';

import FormCreation from './Form';
import CreateTokenDialog from './TokenCreationDialog';

const TokenCreationButton = (): JSX.Element => {
  const { t } = useTranslation();

  const [isCreatingToken, setIsCreatingToken] = useState(false);

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
        labelSave={t(labelCreateNewToken)}
        startIcon={false}
        onClick={createToken}
      />
      {isCreatingToken && (
        <CreateTokenDialog
          renderFormCreation={({ data, isMutating, isRefetching }) => (
            <FormCreation
              closeDialog={closeDialog}
              data={data}
              isDialogOpened={isCreatingToken}
              isMutating={isMutating}
              isRefetching={isRefetching}
            />
          )}
        />
      )}
    </>
  );
};

export default TokenCreationButton;
