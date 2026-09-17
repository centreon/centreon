import AddCircleIcon from '@mui/icons-material/AddCircle';
import { Button, Divider } from '@mui/material';

import { useTranslation } from 'react-i18next';

import { labelAddAHost } from '../../../translatedLabels';
import { useAddButtonStyles } from './HostConfigurationsStyle';

type Props = {
  addButtonDisabled: boolean;
  onAddItem: () => void;
};

const AddButton = ({
  addButtonDisabled,
  onAddItem
}: Props): React.ReactElement => {
  const { t } = useTranslation();
  const { classes } = useAddButtonStyles();

  return (
    <Divider className={classes.addButtonDivider} variant="middle">
      <Button
        aria-label={labelAddAHost}
        className={classes.addButton}
        data-testid={labelAddAHost}
        disabled={addButtonDisabled}
        onClick={onAddItem}
      >
        <AddCircleIcon />
        &nbsp;
        {t(labelAddAHost)}
      </Button>
    </Divider>
  );
};

export default AddButton;
