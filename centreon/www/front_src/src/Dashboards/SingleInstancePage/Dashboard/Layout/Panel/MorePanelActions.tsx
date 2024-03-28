import { useTranslation } from 'react-i18next';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useSearchParams } from 'react-router-dom';

import { Menu } from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import RefreshIcon from '@mui/icons-material/Refresh';
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';

import { ActionsList, ActionsListActionDivider } from '@centreon/ui';

import {
  labelDeleteWidget,
  labelDuplicate,
  labelEditWidget,
  labelRefresh,
  labelViewProperties
} from '../../translatedLabels';
import {
  dashboardAtom,
  switchPanelsEditionModeDerivedAtom,
  widgetToDeleteAtom
} from '../../atoms';
import useWidgetForm from '../../AddEditWidget/useWidgetModal';
import { useCanEditProperties } from '../../hooks/useCanEditDashboard';
import { Panel } from '../../models';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
  duplicate: (event) => void;
  id: string;
  setRefreshCount?: (id) => void;
}

const MorePanelActions = ({
  anchor,
  close,
  id,
  setRefreshCount,
  duplicate
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const [searchParams, setSearchParams] = useSearchParams(
    window.location.search
  );
  const dashboard = useAtomValue(dashboardAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );
  const setWidgetToDelete = useSetAtom(widgetToDeleteAtom);

  const { canEdit } = useCanEditProperties();

  const { openModal } = useWidgetForm();

  const edit = (): void => {
    openModal(dashboard.layout.find((panel) => equals(panel.i, id)) || null);

    close();

    switchPanelsEditionMode(true);
    searchParams.set('edit', 'true');
    setSearchParams(searchParams);
  };

  const refresh = (): void => {
    setRefreshCount?.(id);
    close();
  };

  const openDeleteModal = (): void => {
    const panelToDelete = dashboard.layout.find((panel) =>
      equals(panel.i, id)
    ) as Panel;

    setWidgetToDelete({
      id,
      name: panelToDelete.options?.name
    });
  };

  const displayEditButtons = canEdit;

  const editActions = [
    {
      Icon: EditIcon,
      label: t(labelEditWidget),
      onClick: edit
    },
    ActionsListActionDivider.divider,
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
    ActionsListActionDivider.divider,
    {
      Icon: DeleteIcon,
      label: t(labelDeleteWidget),
      onClick: openDeleteModal,
      variant: 'error'
    }
  ];

  const viewActions = [
    {
      Icon: RefreshIcon,
      label: t(labelRefresh),
      onClick: refresh
    },
    ActionsListActionDivider.divider,
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
