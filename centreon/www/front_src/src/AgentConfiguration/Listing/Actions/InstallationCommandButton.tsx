import Icon from '@mui/icons-material/CodeOffTwoTone';

import { Button } from '@centreon/ui/components';

import { useSetAtom } from 'jotai';
import { ReactElement, useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { pollerToGenerateCommanAtom } from '../../atoms';
import { labelCommand } from '../../translatedLabels';

const InstallationCommandButton = (): ReactElement => {
  const { t } = useTranslation();

  const setOpenFormModal = useSetAtom(pollerToGenerateCommanAtom);

  const displayModal = useCallback(() => setOpenFormModal({}), []);

  return (
    <Button
      data-testid="display-installation-command-modal"
      icon={<Icon />}
      iconVariant="start"
      onClick={displayModal}
      size="medium"
    >
      {t(labelCommand)}
    </Button>
  );
};

export default InstallationCommandButton;
