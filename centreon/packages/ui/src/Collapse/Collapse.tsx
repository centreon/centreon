import ExpandLess from '@mui/icons-material/ExpandLess';
import ExpandMore from '@mui/icons-material/ExpandMore';
import { Collapse as CollapseMui } from '@mui/material';
import Divider from '@mui/material/Divider';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemText, { ListItemTextProps } from '@mui/material/ListItemText';

import { ReactNode, useState } from 'react';
import { useCollapseStyles } from './collapse.styles';

interface Props {
  open?: boolean;
  title: string;
  children: ReactNode;
}

const Collapse = ({
  children,
  title,
  open,
  ...rest
}: Props & ListItemTextProps): JSX.Element => {
  const { classes } = useCollapseStyles();

  const [isOpend, setIsOpened] = useState(open);

  const handleClick = (): void => {
    setIsOpened(!isOpend);
  };

  return (
    <>
      <ListItemButton onClick={handleClick} className={classes.container}>
        <ListItemText primary={title} {...rest} />
        {isOpend ? <ExpandLess /> : <ExpandMore />}
      </ListItemButton>
      <Divider />
      <CollapseMui in={isOpend}>{children}</CollapseMui>
    </>
  );
};

export default Collapse;
