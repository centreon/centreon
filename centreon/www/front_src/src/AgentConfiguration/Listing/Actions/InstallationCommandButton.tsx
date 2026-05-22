import Icon from '@mui/icons-material/CodeOffTwoTone';

import { Button } from '@centreon/ui/components';

import { useSetAtom } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { pollerToGenerateCommandAtom } from '../../atoms';
import { AgentConfigurationListing } from '../../models';
import { labelCommand } from '../../translatedLabels';

const getCentralPoller = (rows: Array<AgentConfigurationListing>) => {
  const allPollers = rows.flatMap((row) => row.pollers || []);
  return allPollers.find((poller) => poller?.isCentral === true) ?? {};
};
interface Props {
  rows: Array<AgentConfigurationListing>;
}

const InstallationCommandButton = ({ rows }: Props): ReactElement => {
  const { t } = useTranslation();

  const setOpenFormModal = useSetAtom(pollerToGenerateCommandAtom);

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
