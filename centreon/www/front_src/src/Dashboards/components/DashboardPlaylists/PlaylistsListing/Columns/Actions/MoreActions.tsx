import { useTranslation } from 'react-i18next';

import DeleteIcon from '@mui/icons-material/Delete';
import PublishLinkIcon from '@mui/icons-material/InsertLinkOutlined';
import { Menu } from '@mui/material';

import {
  ActionsList,
  ActionsListActionDivider,
  ActionsListActions
} from '@centreon/ui';

import { useColumnStyles } from '../useColumnStyles';
import { labelDelete, labelPublishYourPlaylist } from '../../translatedLabels';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
}

const MoreActions = ({ close, anchor }: Props): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = useColumnStyles();

  const actions: ActionsListActions = [
    {
      Icon: PublishLinkIcon,
      label: t(labelPublishYourPlaylist),
      onClick: (): void => undefined
    },
    ActionsListActionDivider.divider,
    {
      Icon: DeleteIcon,
      label: t(labelDelete),
      onClick: (): void => undefined
    }
  ];

  return (
    <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={close}>
      <ActionsList actions={actions} className={classes.moreActions} />
    </Menu>
  );
};

export default MoreActions;
