import {
  DeleteOutlineOutlined as DeleteIcon,
  ToggleOffOutlined as DisableIcon,
  ContentCopyOutlined as DuplicateIcon,
  ToggleOnOutlined as EnableIcon
} from '@mui/icons-material';
import { Menu } from '@mui/material';

import { ActionsList, ActionsListActionDivider } from '@centreon/ui';

import { pipe } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  labelDelete,
  labelDisable,
  labelDuplicate,
  labelEnable
} from '../../../translatedLabels';
import { useActionsStyles } from '../Actions.styles';
import useMassiveActions from './useMassiveActions';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
}

const MoreActions = ({ close, anchor }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles({});

  const { openDeleteModal, openDuplicateModal, enable, disable, isMutating } =
    useMassiveActions();

  return (
    <Menu anchorEl={anchor} onClose={close} open={Boolean(anchor)}>
      <ActionsList
        actions={[
          {
            disable: isMutating,
            Icon: DuplicateIcon,
            label: t(labelDuplicate),
            onClick: pipe(openDuplicateModal, close)
          },
          ActionsListActionDivider.divider,
          {
            disable: isMutating,
            Icon: EnableIcon,
            label: t(labelEnable),
            onClick: pipe(enable, close),
            variant: 'success'
          },
          ActionsListActionDivider.divider,
          {
            disable: isMutating,
            Icon: DisableIcon,
            label: t(labelDisable),
            onClick: pipe(disable, close),
            variant: 'error'
          },
          ActionsListActionDivider.divider,
          {
            disable: isMutating,
            Icon: DeleteIcon,
            label: t(labelDelete),
            onClick: pipe(openDeleteModal, close),
            variant: 'error'
          }
        ]}
        className={classes.ActionsList}
      />
    </Menu>
  );
};

export default MoreActions;
