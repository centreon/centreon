import { pipe } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Delete as DeleteIcon,
  ContentCopy as DuplicateIcon,
  SettingsOutlined as SettingsIcon
} from '@mui/icons-material';
import { Menu } from '@mui/material';

import { ActionsList, ActionsListActionDivider } from '@centreon/ui';

import { labelDelete, labelDuplicate } from '../../../../../translatedLabels';
import { labelEditProperties } from '../../translatedLabels';

import useActions from './useActions';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
  row;
}

const MoreActions = ({ close, anchor, row }: Props): JSX.Element => {
  const { t } = useTranslation();

  const { openDeleteModal, editDashboard, openDuplicateModal } =
    useActions(row);

  return (
    <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={close}>
      <ActionsList
        actions={[
          {
            Icon: SettingsIcon,
            label: t(labelEditProperties),
            onClick: pipe(editDashboard, close)
          },
          ActionsListActionDivider.divider,
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
