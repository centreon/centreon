import { pipe } from 'ramda';
import { useTranslation } from 'react-i18next';

import { ToggleOffRounded, ToggleOnRounded } from '@mui/icons-material';
import { Menu } from '@mui/material';

import { ActionsList, ActionsListActionDivider } from '@centreon/ui';

import { labelDisable, labelEnable } from '../../../translatedLabels';
import useChangeStatus from './useChangeStatus';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
}

const MoreActions = ({ close, anchor }: Props): JSX.Element => {
  const { t } = useTranslation();

  const { enable, disable, isMutating } = useChangeStatus();

  return (
    <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={close}>
      <ActionsList
        actions={[
          {
            Icon: ToggleOnRounded,
            label: t(labelEnable),
            onClick: pipe(enable, close),
            variant: 'success',
            disable: isMutating
          },
          ActionsListActionDivider.divider,
          {
            Icon: ToggleOffRounded,
            label: t(labelDisable),
            onClick: pipe(disable, close),
            disable: isMutating,
            variant: 'error'
          }
        ]}
      />
    </Menu>
  );
};

export default MoreActions;
