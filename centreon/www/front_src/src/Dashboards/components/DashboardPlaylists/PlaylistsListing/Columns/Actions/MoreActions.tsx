import { useTranslation } from 'react-i18next';

import DeleteIcon from '@mui/icons-material/Delete';
import PublishLinkIcon from '@mui/icons-material/InsertLinkOutlined';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import { Menu } from '@mui/material';

import {
  ActionsList,
  ActionsListActionDivider,
  ActionsListActions
} from '@centreon/ui';

import {
  labelDelete,
  labelDuplicate,
  labelPublichYourPlaylist
} from '../../translatedLabels';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
}

const MoreActions = ({ close, anchor }: Props): JSX.Element => {
  const { t } = useTranslation();

  const actions: ActionsListActions = [
    {
      Icon: ContentCopyIcon,
      label: t(labelDuplicate),
      onClick: (): void => undefined
    },
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
      <ActionsList actions={actions} />
    </Menu>
  );
};

export default MoreActions;
