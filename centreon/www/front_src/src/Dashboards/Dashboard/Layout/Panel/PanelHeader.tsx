import { useMemo, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { CardHeader } from '@mui/material';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined';

import { IconButton, useDeepCompare } from '@centreon/ui';

import {
  dashboardAtom,
  duplicatePanelDerivedAtom,
  isEditingAtom
} from '../../atoms';
import { labelMoreActions, labelViewProperties } from '../../translatedLabels';
import { editProperties } from '../../useCanEditDashboard';
import useWidgetForm from '../../AddEditWidget/useWidgetModal';

import { usePanelHeaderStyles } from './usePanelStyles';
import MorePanelActions from './MorePanelActions';

interface PanelHeaderProps {
  id: string;
}

const PanelHeader = ({ id }: PanelHeaderProps): JSX.Element => {
  const { t } = useTranslation();

  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const { classes } = usePanelHeaderStyles();

  const dashboard = useAtomValue(dashboardAtom);
  const isEditing = useAtomValue(isEditingAtom);
  const duplicatePanel = useSetAtom(duplicatePanelDerivedAtom);

  const { canEdit } = editProperties.useCanEditProperties();

  const { openModal } = useWidgetForm();

  const duplicate = (event): void => {
    event.preventDefault();

    duplicatePanel(id);
  };

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  const edit = (): void => {
    openModal(dashboard.layout.find((panel) => equals(panel.i, id)) || null);
    closeMoreActions();
  };

  const panel = useMemo(
    () => dashboard.layout.find((panel) => equals(panel.i, id)),
    useDeepCompare([dashboard.layout])
  );

  const displayEditButtons = canEdit && isEditing;

  return (
    <CardHeader
      action={
        displayEditButtons ? (
          <div className={classes.panelActionsIcons}>
            <IconButton onClick={duplicate}>
              <ContentCopyIcon fontSize="small" />
            </IconButton>
            <IconButton
              ariaLabel={t(labelMoreActions) as string}
              onClick={openMoreActions}
            >
              <MoreVertIcon fontSize="small" />
            </IconButton>
            <MorePanelActions
              anchor={moreActionsOpen}
              close={closeMoreActions}
              id={id}
            />
          </div>
        ) : (
          <div className={classes.panelActionsIcons}>
            <IconButton
              ariaLabel={t(labelViewProperties) as string}
              title={t(labelViewProperties) as string}
              onClick={edit}
            >
              <VisibilityOutlinedIcon fontSize="small" />
            </IconButton>
          </div>
        )
      }
      className={classes.panelHeader}
      title={panel?.options?.name || ''}
    />
  );
};

export default PanelHeader;
