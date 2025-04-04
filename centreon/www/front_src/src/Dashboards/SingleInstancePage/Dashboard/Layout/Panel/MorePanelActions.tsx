import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined';
import { Menu } from '@mui/material';

import { ActionsList, ActionsListActionDivider } from '@centreon/ui';

import useWidgetForm from '../../AddEditWidget/useWidgetModal';
import {
  dashboardAtom,
  switchPanelsEditionModeDerivedAtom,
  widgetToDeleteAtom
} from '../../atoms';
import { useCanEditProperties } from '../../hooks/useCanEditDashboard';
import { Panel } from '../../models';
import {
  labelDeleteWidget,
  labelDuplicate,
  labelEditWidget,
  labelViewProperties
} from '../../translatedLabels';
import { ExpandableData } from './models';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
  duplicate: (event) => void;
  id: string;
  expandableData?: ExpandableData;
}

const MorePanelActions = ({
  anchor,
  close,
  id,
  duplicate,
  expandableData
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const { Icon, label: labelExpand, toggleExpand } = expandableData || {};
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

  const displayEditButtons = canEdit;

  const defaultEditActions = [
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
    }
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
    {
      Icon: VisibilityOutlinedIcon,
      label: t(labelViewProperties),
      onClick: edit
    }
  ];

  const viewActions = !expandableData
    ? defaultViewActions
    : [...defaultViewActions, ...expandableAction];

  return (
    <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={close}>
      <ActionsList actions={displayEditButtons ? editActions : viewActions} />
    </Menu>
  );
};

export default MorePanelActions;
