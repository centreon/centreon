import Icon from '@mui/icons-material/CodeOffTwoTone';

import { Button } from '@centreon/ui/components';

import { useSetAtom } from 'jotai';
import { chain, defaultTo, find, pipe, propEq, propOr } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { pollerToGenerateCommanAtom } from '../../atoms';
import { AgentConfigurationListing } from '../../models';
import { labelCommand } from '../../translatedLabels';

interface Props {
  rows: Array<AgentConfigurationListing>;
}

const InstallationCommandButton = ({ rows }: Props): ReactElement => {
  const { t } = useTranslation();

  const setOpenFormModal = useSetAtom(pollerToGenerateCommanAtom);

  const displayModal = (): void => {

    setOpenFormModal(getCentralPoller(rows) as { id?: number; name?: string });
  };

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
