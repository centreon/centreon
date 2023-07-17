import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import { Avatar, CardActionArea, Typography } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';

import { labelAddAWidget } from '../translatedLabels';
import { useAddWidgetPanelStyles } from '../Layout/Panel/usePanelStyles';
import { isEditingAtom } from '../atoms';

import useAddWidget from './useAddWidget';

const AddWidgetPanel = (): JSX.Element => {
  const { t } = useTranslation();

  const isEditing = useAtomValue(isEditingAtom);

  const { classes } = useAddWidgetPanelStyles();

  const { openModal } = useAddWidget();

  return (
    <CardActionArea
      disabled={!isEditing}
      sx={{ height: '100%', width: '100%' }}
      onClick={openModal}
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
