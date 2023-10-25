import { useTranslation } from 'react-i18next';
import { useAtomValue, useAtom } from 'jotai';
import { equals } from 'ramda';

import { Menu } from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import RefreshIcon from '@mui/icons-material/Refresh';
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';

import {
  ActionsList,
  ActionsListActions,
  ActionsListActionDivider
} from '@centreon/ui';
import { ConfirmationTooltip } from '@centreon/ui/components';

import {
  labelCancel,
  labelDelete,
  labelDeleteWidget,
  labelDoYouWantToDeleteThisWidget,
  labelDuplicate,
  labelEditWidget,
  labelRefresh,
  labelViewProperties
} from '../../translatedLabels';
import { dashboardAtom, isEditingAtom } from '../../atoms';
import useWidgetForm from '../../AddEditWidget/useWidgetModal';
import { editProperties } from '../../hooks/useCanEditDashboard';
import useDeleteWidgetModal from '../../hooks/useDeleteWidget';

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

  const [, isEditing] = useAtom(isEditingAtom);

  const dashboard = useAtomValue(dashboardAtom);

  const { deleteWidget } = useDeleteWidgetModal();

  const { canEdit } = editProperties.useCanEditProperties();

  const { openModal } = useWidgetForm();

  const edit = (): void => {
    isEditing(true);
    openModal(dashboard.layout.find((panel) => equals(panel.i, id)) || null);
    close();
  };

  const refresh = (): void => {
    setRefreshCount(id);
    close();
  };

  const displayEditButtons = canEdit;

  const editActions = (openConfirmationTooltip): ActionsListActions => [
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
      onClick: openConfirmationTooltip
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

  const confirmationLabels = {
    cancel: t(labelCancel),
    confirm: {
      label: t(labelDelete),
      secondaryLabel: t(labelDoYouWantToDeleteThisWidget)
    }
  };

  return (
    <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={close}>
      <ConfirmationTooltip
        confirmVariant="error"
        labels={confirmationLabels}
        onConfirm={deleteWidget(id)}
      >
        {(openConfirmationTooltip) => (
          <ActionsList
            actions={
              displayEditButtons
                ? editActions(openConfirmationTooltip)
                : viewActions
            }
          />
        )}
      </ConfirmationTooltip>
    </Menu>
  );
};

export default MorePanelActions;
