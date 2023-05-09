import React from 'react';
import { useStyles } from './ListItem.styles';


type ListItemProps = {
  title: string;
  description: string;
};

const ListItem: React.FC<ListItemProps> = ({
  title,
  description,
}): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.listItem}>
      <h3>{title}</h3>
      <p>{description}</p>
    </div>
  );
};

export { ListItem };