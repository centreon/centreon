import { useTranslation } from 'react-i18next';
import { pipe } from 'ramda';

import {
  Delete as DeleteIcon,
  ContentCopy as DuplicateIcon
} from '@mui/icons-material';
import { Menu } from '@mui/material';

import { ActionsList, ActionsListActionDivider } from '@centreon/ui';

import { labelDuplicate, labelDelete } from '../../../translatedLabels';

import useActions from './useActions';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
  row;
}

const MoreActions = ({ close, anchor, row }: Props): JSX.Element => {
  const { t } = useTranslation();

  const { openDeleteModal, openDuplicateModal } = useActions(row);

  return (
    <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={close}>
      <ActionsList
        actions={[
          {
            Icon: DuplicateIcon,
            label: t(labelDuplicate),
            onClick: pipe(openDuplicateModal, close)
          },
          ActionsListActionDivider.divider,
          {
            Icon: DeleteIcon,
            label: t(labelDelete),
            onClick: pipe(openDeleteModal, close),
            variant: 'error'
          }
        ]}
      />
    </Menu>
  );
};

export default MoreActions;
