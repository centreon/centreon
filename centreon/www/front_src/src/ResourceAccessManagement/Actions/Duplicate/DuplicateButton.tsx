import { useTranslation } from 'react-i18next';

import ContentCopyIcon from '@mui/icons-material/ContentCopy';

import { IconButton } from '@centreon/ui';

import { labelDuplicate } from '../../translatedLabels';

interface Props {
  ariaLabel?: string;
  className?: string;
  disabled?: boolean;
  onClick: () => void;
}

const DuplicateButton = ({
  disabled = false,
  className,
  ariaLabel,
  onClick
}: Props): React.JSX.Element => {
  const { t } = useTranslation();

  return (
    <IconButton
      ariaLabel={ariaLabel}
      disabled={disabled}
      title={t(labelDuplicate) as string}
      onClick={onClick}
    >
      <ContentCopyIcon className={className} />
    </IconButton>
  );
};

export default DuplicateButton;
