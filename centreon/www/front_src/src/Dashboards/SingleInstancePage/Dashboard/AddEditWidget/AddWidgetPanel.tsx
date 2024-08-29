import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';
import { Avatar, CardActionArea, Typography } from '@mui/material';

import { useAddWidgetPanelStyles } from '../Layout/Panel/usePanelStyles';
import { isEditingAtom } from '../atoms';
import { labelAddAWidget } from '../translatedLabels';

import useWidgetForm from './useWidgetModal';

const AddWidgetPanel = (): JSX.Element => {
  const { t } = useTranslation();

  const isEditing = useAtomValue(isEditingAtom);

  const { classes } = useAddWidgetPanelStyles();

  const { openModal } = useWidgetForm();

  return (
    <CardActionArea
      disabled={!isEditing}
      sx={{ height: '100%', width: '100%' }}
      onClick={() => openModal(null)}
    >
      <div className={classes.addWidgetPanel}>
        <Typography variant="h5">{t(labelAddAWidget)}</Typography>
        <Avatar className={classes.avatar}>
          <AddIcon color="inherit" />
        </Avatar>
      </div>
    </CardActionArea>
  );
};

export default AddWidgetPanel;
