import { useTranslation } from 'react-i18next';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';

import { Menu } from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import RefreshIcon from '@mui/icons-material/Refresh';
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';

import { ActionsList } from '@centreon/ui';

import {
  labelDeleteWidget,
  labelDuplicate,
  labelEditWidget,
  labelRefresh,
  labelViewProperties
} from '../../translatedLabels';
import { askDeletePanelAtom, dashboardAtom, isEditingAtom } from '../../atoms';
import useWidgetForm from '../../AddEditWidget/useWidgetModal';
import { editProperties } from '../../useCanEditDashboard';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
  duplicate: (event) => void;
  id: string;
  setRefreshCount: (id) => void;
}

const MorePanelActions = ({
  anchor,
  close,
  id,
  setRefreshCount,
  duplicate
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const dashboard = useAtomValue(dashboardAtom);
  const isEditing = useAtomValue(isEditingAtom);
  const setAskDeletePanel = useSetAtom(askDeletePanelAtom);

  const { canEdit } = editProperties.useCanEditProperties();

  const { openModal } = useWidgetForm();

  const remove = (): void => {
    setAskDeletePanel(id);
    close();
  };

  const edit = (): void => {
    openModal(dashboard.layout.find((panel) => equals(panel.i, id)) || null);
    close();
  };

  const refresh = (): void => {
    setRefreshCount(id);
    close();
  };

  const displayEditButtons = canEdit && isEditing;

  const editActions = [
    {
      Icon: EditIcon,
      label: t(labelEditWidget),
      onClick: edit
    },
    'divider' as const,
    {
      Icon: RefreshIcon,
      label: t(labelRefresh),
      onClick: refresh
    },
    {
      Icon: ContentCopyIcon,
      label: t(labelDuplicate),
      onClick: duplicate
    },
    'divider' as const,
    {
      Icon: DeleteIcon,
      label: t(labelDeleteWidget),
      onClick: remove
    }
  ];

  const viewActions = [
    {
      Icon: RefreshIcon,
      label: t(labelRefresh),
      onClick: refresh
    },
    'divider' as const,
    {
      Icon: VisibilityOutlinedIcon,
      label: t(labelViewProperties),
      onClick: edit
    }
  ];

  return (
    <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={close}>
      <ActionsList actions={displayEditButtons ? editActions : viewActions} />
    </Menu>
  );
};

export default MorePanelActions;
