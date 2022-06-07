import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ExpandLessIcon from '@mui/icons-material/ExpandLess';

interface Props {
  onClick: () => void;
  rotate: boolean;
}

const IconToggleSubmenu = ({ rotate, onClick }: Props): JSX.Element => {
  const ExpandIcon = rotate ? ExpandLessIcon : ExpandMoreIcon;

  return (
    <ExpandIcon
      style={{ color: '#FFFFFF', cursor: 'pointer' }}
      onClick={onClick}
    />
  );
};

export default IconToggleSubmenu;
