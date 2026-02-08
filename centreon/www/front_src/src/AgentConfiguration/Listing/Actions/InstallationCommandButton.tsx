import { ReactElement, useCallback } from 'react';

import { Button } from '@centreon/ui/components';
import Icon from '@mui/icons-material/CodeOffTwoTone';

import { useSetAtom } from 'jotai';
// import { useTranslation } from 'react-i18next';

import { pollerToGenerateCommanAtom } from '../../atoms';
// import { labelAdd } from '../../translatedLabels';

const InstallationCommandButton = (): ReactElement => {
  // const { t } = useTranslation();

  const setOpenFormModal = useSetAtom(pollerToGenerateCommanAtom);

  const displayModal = useCallback(
    () => setOpenFormModal({ id: 1, name: 'hello' }),
    []
  );

  return (
    <Button
      data-testid="display-installation-command-modal"
      icon={<Icon />}
      iconVariant="start"
      onClick={displayModal}
      size="medium"
    >
      Command
    </Button>
  );
};

export default InstallationCommandButton;
