import { pipe } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  DeleteOutlineOutlined as DeleteIcon,
  ToggleOffOutlined as DisableIcon,
  ContentCopyOutlined as DuplicateIcon,
  ToggleOnOutlined as EnableIcon
} from '@mui/icons-material';
import { Menu } from '@mui/material';

import { ActionsList, ActionsListActionDivider } from '@centreon/ui';

import { useActionsStyles } from '../Actions.styles';
import useMassiveActions from './useMassiveActions';

import {
  labelDelete,
  labelDisable,
  labelDuplicate,
  labelEnable
} from '../../../translatedLabels';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
}

const MoreActions = ({ close, anchor }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles();

  const { openDeleteModal, openDuplicateModal, enable, disable, isMutating } =
    useMassiveActions();

  return (
    <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={close}>
      <ActionsList
        className={classes.ActionsList}
        actions={[
          {
            Icon: DuplicateIcon,
            label: t(labelDuplicate),
            onClick: pipe(openDuplicateModal, close),
            disable: isMutating
          },
          ActionsListActionDivider.divider,
          {
            Icon: EnableIcon,
            label: t(labelEnable),
            onClick: pipe(enable, close),
            disable: isMutating,
            variant: 'success'
          },
          ActionsListActionDivider.divider,
          {
            Icon: DisableIcon,
            label: t(labelDisable),
            onClick: pipe(disable, close),
            disable: isMutating,
            variant: 'error'
          },
          ActionsListActionDivider.divider,
          {
            Icon: DeleteIcon,
            label: t(labelDelete),
            onClick: pipe(openDeleteModal, close),
            variant: 'error',
            disable: isMutating
          }
        ]}
      />
    </Menu>
  );
};

export default MoreActions;
