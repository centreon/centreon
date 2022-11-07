<<<<<<< HEAD
import { useCallback } from 'react';

import { useTranslation } from 'react-i18next';
import { map, find, equals, path } from 'ramda';
import { useUpdateAtom } from 'jotai/utils';
import { useAtom } from 'jotai';
import { rectIntersection } from '@dnd-kit/core';
import { rectSortingStrategy } from '@dnd-kit/sortable';

import { Typography, LinearProgress, Stack } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

import {
  MemoizedSectionPanel as SectionPanel,
  useRequest,
  RootComponentProps,
  SortableItems,
} from '@centreon/ui';

import { labelEditFilters } from '../../translatedLabels';
import { patchFilter } from '../api';
import { customFiltersAtom, editPanelOpenAtom } from '../filterAtoms';
import { Filter } from '../models';
import { Criteria } from '../Criterias/models';

import SortableContent from './SortableContent';
=======
import * as React from 'react';

import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';
import { useTranslation } from 'react-i18next';
import { move, isNil } from 'ramda';

import {
  Typography,
  makeStyles,
  LinearProgress,
  Paper,
} from '@material-ui/core';
import MoveIcon from '@material-ui/icons/UnfoldMore';

import { MemoizedSectionPanel as SectionPanel, useRequest } from '@centreon/ui';

import { useResourceContext } from '../../Context';
import { labelEditFilters } from '../../translatedLabels';
import { patchFilter } from '../api';

import EditFilterCard from './EditFilterCard';
>>>>>>> centreon/dev-21.10.x

const useStyles = makeStyles((theme) => ({
  container: {
    width: '100%',
  },
<<<<<<< HEAD
=======
  filterCard: {
    alignItems: 'center',
    display: 'grid',
    gridGap: theme.spacing(2),
    gridTemplateColumns: '1fr auto',
    padding: theme.spacing(1),
  },
>>>>>>> centreon/dev-21.10.x
  filters: {
    display: 'grid',
    gridAutoFlow: 'row',
    gridGap: theme.spacing(3),
    gridTemplateRows: '1fr',
    width: '100%',
  },
  header: {
    alignItems: 'center',
    display: 'flex',
    height: '100%',
    justifyContent: 'center',
  },
  loadingIndicator: {
    height: theme.spacing(1),
    marginBottom: theme.spacing(1),
    width: '100%',
  },
}));

const EditFiltersPanel = (): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();

<<<<<<< HEAD
=======
  const { customFilters, setEditPanelOpen, setCustomFilters } =
    useResourceContext();

>>>>>>> centreon/dev-21.10.x
  const { sendRequest, sending } = useRequest({
    request: patchFilter,
  });

<<<<<<< HEAD
  const [customFilters, setCustomFilters] = useAtom(customFiltersAtom);
  const setEditPanelOpen = useUpdateAtom(editPanelOpenAtom);

=======
>>>>>>> centreon/dev-21.10.x
  const closeEditPanel = (): void => {
    setEditPanelOpen(false);
  };

<<<<<<< HEAD
  const dragEnd = ({ items, event }): void => {
    const reorderedCutomFilters = map((id) => {
      const filter = find(
        (customFilter) => equals(Number(customFilter.id), Number(id)),
        customFilters,
      ) as Filter;

      return {
        ...filter,
        order: items.indexOf(id),
      };
    }, items);

    setCustomFilters(reorderedCutomFilters);

    const activeId = path(['active', 'id'], event);
    const destinationIndex = path(
      ['active', 'data', 'current', 'sortable', 'index'],
      event,
    ) as number;

    sendRequest({ id: activeId, order: destinationIndex + 1 });
  };

  const displayedFilters = map(
    ({ id, ...other }) => ({ ...other, id: `${id}` }),
    customFilters,
  );

  const RootComponent = useCallback(
    ({ children }: RootComponentProps): JSX.Element => (
      <Stack spacing={2}>{children}</Stack>
    ),
    [],
  );

=======
  const onDragEnd = ({ draggableId, source, destination }): void => {
    const id = Number(draggableId);

    if (isNil(destination)) {
      return;
    }

    const reordedCustomFilters = move(
      source.index,
      destination.index,
      customFilters,
    );

    setCustomFilters(reordedCustomFilters);

    sendRequest({ id, order: destination.index });
  };

>>>>>>> centreon/dev-21.10.x
  const sections = [
    {
      expandable: false,
      id: 'edit',
      section: (
        <div className={classes.container}>
          <div className={classes.loadingIndicator}>
            {sending && <LinearProgress style={{ width: '100%' }} />}
          </div>
<<<<<<< HEAD
          <SortableItems<{
            criterias: Array<Criteria>;
            id: string;
            name: string;
          }>
            updateSortableItemsOnItemsChange
            Content={SortableContent}
            RootComponent={RootComponent}
            collisionDetection={rectIntersection}
            itemProps={['criterias', 'id', 'name']}
            items={displayedFilters}
            sortingStrategy={rectSortingStrategy}
            onDragEnd={dragEnd}
          />
=======
          <DragDropContext onDragEnd={onDragEnd}>
            <Droppable droppableId="droppable">
              {(droppable): JSX.Element => (
                <div
                  className={classes.filters}
                  ref={droppable.innerRef}
                  {...droppable.droppableProps}
                >
                  {customFilters?.map((filter, index) => (
                    <Draggable
                      draggableId={`${filter.id}`}
                      index={index}
                      key={filter.id}
                    >
                      {(draggable): JSX.Element => (
                        <Paper
                          square
                          className={classes.filterCard}
                          ref={draggable.innerRef}
                          {...draggable.draggableProps}
                        >
                          <EditFilterCard filter={filter} />
                          <div {...draggable.dragHandleProps}>
                            <MoveIcon />
                          </div>
                        </Paper>
                      )}
                    </Draggable>
                  ))}
                  {droppable.placeholder}
                </div>
              )}
            </Droppable>
          </DragDropContext>
>>>>>>> centreon/dev-21.10.x
        </div>
      ),
    },
  ];

  const header = (
    <div className={classes.header}>
      <Typography align="center" variant="h6">
        {t(labelEditFilters)}
      </Typography>
    </div>
  );

  return (
    <SectionPanel
      header={header}
      memoProps={[customFilters]}
      sections={sections}
      onClose={closeEditPanel}
    />
  );
};

export default EditFiltersPanel;
