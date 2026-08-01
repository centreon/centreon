// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import UpdateIcon from '@mui/icons-material/Update';
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined';
import { Menu } from '@mui/material';

import { ActionsList, ActionsListActionDivider } from '@centreon/ui';

import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import useWidgetForm from '../../AddEditWidget/useWidgetModal';
import {
  dashboardAtom,
  isEditingAtom,
  switchPanelsEditionModeDerivedAtom,
  widgetToDeleteAtom
} from '../../atoms';
import { useCanEditProperties } from '../../hooks/useCanEditDashboard';
import useResetDashboardFromSavedState from '../../hooks/useResetDashboardFromSavedState';
import { Panel } from '../../models';
import {
  labelDeleteWidget,
  labelDuplicate,
  labelEditWidget,
  labelRefresh,
  labelViewProperties
} from '../../translatedLabels';
import { ExpandableData } from './models';

interface ResourceStatusAction {
  Icon: unknown;
  label: string;
  onClick: () => void;
}

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
  duplicate: (event: React.MouseEvent) => void;
  id: string;
  expandableData?: ExpandableData;
  onRefresh?: () => void;
  resourceStatusAction?: ResourceStatusAction;
}

const MorePanelActions = ({
  anchor,
  close,
  id,
  duplicate,
  expandableData,
  onRefresh,
  resourceStatusAction
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const { Icon, label: labelExpand, toggleExpand } = expandableData || {};
  const [searchParams, setSearchParams] = useSearchParams(
    window.location.search
  );
  const dashboard = useAtomValue(dashboardAtom);
  const isEditing = useAtomValue(isEditingAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );
  const setWidgetToDelete = useSetAtom(widgetToDeleteAtom);

  const { canEdit } = useCanEditProperties();

  const { openModal } = useWidgetForm();

  const resetDashboardFromSavedState = useResetDashboardFromSavedState();

  const edit = (): void => {
    const layout =
      canEdit && !isEditing ? resetDashboardFromSavedState() : dashboard.layout;

    openModal(layout.find((panel) => equals(panel.i, id)) || null);

    close();

    switchPanelsEditionMode(true);
    searchParams.set('edit', 'true');
    setSearchParams(searchParams);
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

  const handleExpandableAction = () => {
    toggleExpand?.();
    close();
  };

  const handleRefresh = (): void => {
    onRefresh?.();
    close();
  };

  const displayEditButtons = canEdit;

  const seeMoreResourceAction = resourceStatusAction
    ? [ActionsListActionDivider.divider, resourceStatusAction]
    : [];

  const refreshAction = onRefresh
    ? [
        {
          Icon: UpdateIcon,
          label: t(labelRefresh),
          onClick: handleRefresh
        },
        ActionsListActionDivider.divider
      ]
    : [];

  const defaultEditActions = [
    ...refreshAction,
    {
      Icon: EditIcon,
      label: t(labelEditWidget),
      onClick: edit
    },
    ActionsListActionDivider.divider,
    {
      Icon: ContentCopyIcon,
      label: t(labelDuplicate),
      onClick: duplicate
    },
    ...seeMoreResourceAction
  ];

  const deleteAction = [
    ActionsListActionDivider.divider,
    {
      Icon: DeleteIcon,
      label: t(labelDeleteWidget),
      onClick: openDeleteModal,
      variant: 'error'
    }
  ];

  const expandableAction = [
    ActionsListActionDivider.divider,
    {
      Icon,
      label: t(labelExpand as string),
      onClick: handleExpandableAction
    }
  ];

  const editActions = !expandableData
    ? [...defaultEditActions, ...deleteAction]
    : [...defaultEditActions, ...expandableAction, ...deleteAction];

  const defaultViewActions = [
    ...refreshAction,
    {
      Icon: VisibilityOutlinedIcon,
      label: t(labelViewProperties),
      onClick: edit
    },
    ...seeMoreResourceAction
  ];

  const viewActions = !expandableData
    ? defaultViewActions
    : [...defaultViewActions, ...expandableAction];

  return (
    <Menu anchorEl={anchor} onClose={close} open={Boolean(anchor)}>
      <ActionsList actions={displayEditButtons ? editActions : viewActions} />
    </Menu>
  );
};

export default MorePanelActions;
