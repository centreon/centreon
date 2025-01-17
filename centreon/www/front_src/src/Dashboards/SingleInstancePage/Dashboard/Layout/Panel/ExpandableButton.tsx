import { IconButton } from '@centreon/ui';
import CloseIcon from '@mui/icons-material/Close';
import { useTranslation } from 'react-i18next';
import { ExpandableData } from './models';

interface Props {
  expandableData?: ExpandableData;
}

const ExpandableButton = ({ expandableData }: Props): JSX.Element => {
  const { t } = useTranslation();

  const { isExpanded, label, toggleExpand, Icon } = expandableData || {};

  return (
    <>
      {expandableData && (
        <IconButton ariaLabel={t(label as string)} onClick={toggleExpand}>
          {isExpanded ? (
            <CloseIcon fontSize="small" />
          ) : (
            <Icon fontSize="small" />
          )}
        </IconButton>
      )}
    </>
  );
};

export default ExpandableButton;
