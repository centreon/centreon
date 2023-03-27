import { useMemo } from 'react';

import { Fade, List, ListItem, useTheme } from '@mui/material';

interface Props {
  columns?: number;
  names: Array<string>;
}

const NamesList = ({ names, columns = 2 }: Props): JSX.Element => {
  const theme = useTheme();

  const sortedNames = useMemo(() => names.sort(), [names]);

  return (
    <List
      dense
      sx={{
        columnGap: 2,
        display: 'grid',
        gridTemplateColumns: `repeat(${columns}, max-content)`
      }}
    >
      {sortedNames.map((name, idx) => (
        <Fade
          in
          key={name}
          style={{ transitionDelay: `${idx * 30}ms` }}
          timeout={theme.transitions.duration.enteringScreen}
        >
          <ListItem disableGutters disablePadding>
            {name}
          </ListItem>
        </Fade>
      ))}
    </List>
  );
};

export default NamesList;
