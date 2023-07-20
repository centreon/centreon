import { useTranslation } from 'react-i18next';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';

import { Menu } from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';

import { ActionsList } from '@centreon/ui';

import { labelDeleteWidget, labelEditWidget } from '../../translatedLabels';
import { askDeletePanelAtom, dashboardAtom } from '../../atoms';
import useWidgetForm from '../../AddEditWidget/useWidgetModal';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
  id: string;
}

const MorePanelActions = ({ anchor, close, id }: Props): JSX.Element => {
  const { t } = useTranslation();

  const dashboard = useAtomValue(dashboardAtom);
  const setAskDeletePanel = useSetAtom(askDeletePanelAtom);

  const { openModal } = useWidgetForm();

  const remove = (): void => {
    setAskDeletePanel(id);
  };

  const edit = (): void => {
    openModal(dashboard.layout.find((panel) => equals(panel.i, id)) || null);
  };

  return (
    <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={close}>
      <ActionsList
        actions={[
          {
            Icon: EditIcon,
            label: t(labelEditWidget),
            onClick: edit
          },
          'divider',
          {
            Icon: DeleteIcon,
            label: t(labelDeleteWidget),
            onClick: remove
          }
        ]}
      />
    </Menu>
  );
};

export default MorePanelActions;
