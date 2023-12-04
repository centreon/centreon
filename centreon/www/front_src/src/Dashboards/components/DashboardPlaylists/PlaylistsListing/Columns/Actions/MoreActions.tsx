import { useTranslation } from 'react-i18next';

import DeleteIcon from '@mui/icons-material/Delete';
import PublishLinkIcon from '@mui/icons-material/InsertLinkOutlined';
import { Menu } from '@mui/material';
import {useColumnStyles} from "../useColumnStyles"

import {
  ActionsList,
  ActionsListActionDivider,
  ActionsListActions
} from '@centreon/ui';

import {
  labelDelete,
  labelPublichYourPlaylist
} from '../../translatedLabels';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
}

const MoreActions = ({ close, anchor }: Props): JSX.Element => {
  const { t } = useTranslation();

  const {classes} = useColumnStyles()

  const actions: ActionsListActions = [
    {
      Icon: PublishLinkIcon,
      label: t(labelPublichYourPlaylist),
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
      <ActionsList className={classes.moreActions} actions={actions} />
    </Menu>
  );
};

export default MoreActions;
