import { useTranslation } from 'react-i18next';

import DeleteIcon from '@mui/icons-material/Delete';

import { IconButton } from '@centreon/ui';

import { labelDelete } from '../../translatedLabels';

const ActionsColumn = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <IconButton
      ariaLabel={t(labelDelete) as string}
      data-testid={labelDelete}
      size="large"
      sx={{ marginLeft: 1 }}
      title={t(labelDelete)}
      onClick={() => {}}
    >
      <DeleteIcon />
    </IconButton>
  );
};
export default ActionsColumn;
