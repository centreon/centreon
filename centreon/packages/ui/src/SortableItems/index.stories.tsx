import * as React from 'react';

import { ComponentMeta, ComponentStory } from '@storybook/react';
import { DraggableSyntheticListeners, rectIntersection } from '@dnd-kit/core';
import {
  horizontalListSortingStrategy,
  rectSortingStrategy,
  SortingStrategy,
  verticalListSortingStrategy
} from '@dnd-kit/sortable';
import { not } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Paper, Typography, Grid, IconButton } from '@mui/material';
import MoreVertIcon from '@mui/icons-material/MoreVert';

import SortableItems, { RootComponentProps } from '.';

export default {
  component: SortableItems,
  title: 'SortableItems'
} as ComponentMeta<typeof SortableItems>;

interface Entity {
  id: string;
  name: string;
  xs: number;
}

const items: Array<Entity> = [
  {
    id: 'label',
    name: 'This is a label content',
    xs: 6
  },
  {
    id: 'description',
    name: 'This is a description content',
    xs: 6
  },
  {
    id: 'custom',
    name: 'This is a custom content',
    xs: 12
  },
  {
    id: 'FQDN',
    name: 'This is a FQDN content',
    xs: 12
  },
  {
    id: 'music',
    name: 'This is a music content',
    xs: 3
  },
  {
    id: 'AWS',
    name: 'This is a AWS content',
    xs: 9
  }
];

interface StylesProps {
  isDragging: boolean;
}

const useContentStyles = makeStyles<StylesProps>()((theme, { isDragging }) => ({
  content: {
    '&:hover': {
      boxShadow: theme.shadows[3]
    },
    cursor: isDragging ? 'grabbing' : 'grab',
    padding: theme.spacing(1)
  },
  contentWithHandler: {
    '&:hover': {
      boxShadow: theme.shadows[3]
    },
    display: 'grid',
    gridTemplateColumns: 'min-content auto',
    padding: theme.spacing(1)
  },

  handler: {
    cursor: isDragging ? 'grabbing' : 'grab'
  }
}));

interface ContentProps extends Entity {
  attributes;
  isDragging: boolean;
  itemRef: React.RefObject<HTMLDivElement>;
  listeners: DraggableSyntheticListeners;
  style;
}

const Content = ({
  listeners,
  attributes,
  style,
  isDragging,
  itemRef,
  name
}: ContentProps): JSX.Element => {
  const { classes } = useContentStyles({ isDragging });

  return (
    <Paper
      style={style}
      {...listeners}
      {...attributes}
      className={classes.content}
      ref={itemRef}
      tabIndex={0}
    >
      <Typography>{name as string}</Typography>
    </Paper>
  );
};

const ContentWithHandler = ({
  listeners,
  attributes,
  style,
  isDragging,
  itemRef,
  name
}: ContentProps): JSX.Element => {
  const { classes } = useContentStyles({ isDragging });

  return (
    <div style={style}>
      <Paper
        {...attributes}
        className={classes.contentWithHandler}
        ref={itemRef}
      >
        <IconButton size="small" {...listeners} className={classes.handler}>
          <MoreVertIcon fontSize="small" />
        </IconButton>
        <Typography>{name as string}</Typography>
      </Paper>
    </div>
  );
};

const useStyles = makeStyles()((theme) => ({
  gridContainer: {
    columnGap: theme.spacing(1),
    display: 'grid',
    grid: 'auto-flow / 1fr 1.2fr 1.1fr',
    height: '100%',
    rowGap: theme.spacing(1),
    width: '550px'
  },
  horizontalContainer: {
    columnGap: theme.spacing(1),
    display: 'flex',
    flexDirection: 'row',
    height: '40px',
    width: '100%'
  },
  verticalContainer: {
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    rowGap: theme.spacing(1),
    width: '550px'
  }
}));

interface StoryProps {
  direction: string;
  handler?: boolean;
  sortingStrategy: SortingStrategy;
}

const Story = ({
  direction,
  sortingStrategy,
  handler = false
}: StoryProps): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes[direction]}>
      <SortableItems
        Content={handler ? ContentWithHandler : Content}
        collisionDetection={rectIntersection}
        itemProps={['name']}
        items={items}
        sortingStrategy={sortingStrategy}
      />
    </div>
  );
};

const TemplateSortableItems: ComponentStory<typeof SortableItems> = (args) => (
  <Story direction="horizontalContainer" {...args} />
);

export const PlaygroundSortableItems = TemplateSortableItems.bind({});

export const vertical = (): JSX.Element => (
  <Story
    direction="verticalContainer"
    sortingStrategy={verticalListSortingStrategy}
  />
);

export const horizontal = (): JSX.Element => (
  <Story
    direction="horizontalContainer"
    sortingStrategy={horizontalListSortingStrategy}
  />
);

export const grid = (): JSX.Element => (
  <Story direction="gridContainer" sortingStrategy={rectSortingStrategy} />
);

const ContentWithGrid = ({
  listeners,
  attributes,
  style,
  isDragging,
  itemRef,
  name,
  xs
}: ContentProps): JSX.Element => {
  const { classes } = useContentStyles({ isDragging });

  return (
    <Grid item style={style} xs={xs} {...listeners} {...attributes}>
      <Paper className={classes.content} ref={itemRef}>
        <Typography>{name as string}</Typography>
      </Paper>
    </Grid>
  );
};

const RootComponent = ({
  children,
  isInDragOverlay
}: RootComponentProps): JSX.Element => (
  <Grid container spacing={1} style={{ width: '550px' }}>
    {not(isInDragOverlay) && (
      <Grid item xs={12}>
        <Typography align="center">This item cannot move</Typography>
      </Grid>
    )}
    {children}
  </Grid>
);

const StoryWithRootComponent = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.verticalContainer}>
      <SortableItems
        Content={ContentWithGrid}
        RootComponent={RootComponent}
        collisionDetection={rectIntersection}
        itemProps={['name', 'xs']}
        items={items}
        sortingStrategy={rectSortingStrategy}
      />
    </div>
  );
};

export const gridWithRootComponent = (): JSX.Element => (
  <StoryWithRootComponent />
);

export const gridWithHandlers = (): JSX.Element => (
  <Story
    handler
    direction="gridContainer"
    sortingStrategy={rectSortingStrategy}
  />
);
