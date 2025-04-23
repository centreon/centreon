import { useTranslation } from 'react-i18next';

import DeleteIcon from '@mui/icons-material/DeleteOutline';

import { IconButton } from '@centreon/ui';

import { labelDelete } from '../../translatedLabels';

interface Props {
  ariaLabel?: string;
  className?: string;
  disabled?: boolean;
  iconClassName?: string;
  onClick: () => void;
}

const DeleteButton = ({
  disabled = false,
  ariaLabel,
  className,
  iconClassName,
  onClick
}: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <IconButton
      ariaLabel={ariaLabel}
      className={className}
      disabled={disabled}
      title={t(labelDelete) as string}
      onClick={onClick}
    >
      <DeleteIcon className={iconClassName} />
    </IconButton>
  );
};

export default DeleteButton;
