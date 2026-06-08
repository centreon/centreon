import CloseIcon from '@mui/icons-material/Close';

import { ConfirmDialog, useRequest, useSnackbar } from '@centreon/ui';

import {
  closestCenter,
  DndContext,
  type DragEndEvent,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors
} from '@dnd-kit/core';
import {
  arrayMove,
  horizontalListSortingStrategy,
  SortableContext,
  useSortable
} from '@dnd-kit/sortable';
import { useAtom, useSetAtom } from 'jotai';
import { equals, findIndex, omit, reject, update } from 'ramda';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  labelAskDelete,
  labelCancel,
  labelDelete,
  labelFilterDeleted,
  labelFilterUpdated
} from '../translatedLabels';
import { deleteFilter, patchFilter, updateFilter } from './api';
import {
  appliedFilterAtom,
  applyFilterDerivedAtom,
  currentFilterAtom,
  customFiltersAtom
} from './filterAtoms';
import {
  allFilter,
  type Filter,
  newFilter,
  resourceProblemsFilter,
  unhandledProblemsFilter
} from './models';

const standardFilters = [
  allFilter,
  unhandledProblemsFilter,
  resourceProblemsFilter
];

const sameId =
  (filter: Filter) =>
  (other: Filter): boolean =>
    equals(Number(filter.id), Number(other.id));

const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(0.5),
    maxWidth: '100%',
    overflowX: 'auto',
    paddingBottom: theme.spacing(0.5)
  },
  deleteButton: {
    '&:hover': {
      color: theme.palette.error.main
    },
    alignItems: 'center',
    background: 'transparent',
    border: 0,
    color: theme.palette.text.secondary,
    cursor: 'pointer',
    display: 'inline-flex',
    fontSize: theme.spacing(2),
    padding: 0,
    transition: 'opacity 0.15s'
  },
  label: {
    background: 'transparent',
    border: 0,
    color: 'inherit',
    cursor: 'pointer',
    font: 'inherit',
    padding: 0
  },
  renameInput: {
    background: 'transparent',
    border: 0,
    borderBottom: `1px solid ${theme.palette.primary.main}`,
    color: 'inherit',
    font: 'inherit',
    outline: 0,
    padding: 0
  },
  tab: {
    alignItems: 'center',
    background: 'transparent',
    border: 0,
    borderBottom: '2px solid transparent',
    color: theme.palette.text.secondary,
    cursor: 'pointer',
    display: 'inline-flex',
    fontFamily: theme.typography.fontFamily,
    fontSize: theme.typography.body2.fontSize,
    gap: theme.spacing(0.75),
    paddingBlock: theme.spacing(0.75),
    paddingInline: theme.spacing(1.25),
    whiteSpace: 'nowrap'
  },
  tabActive: {
    borderBottom: `2px solid ${theme.palette.primary.main}`,
    color: theme.palette.primary.main,
    fontWeight: theme.typography.fontWeightBold
  }
}));

interface CustomFilterTabProps {
  filter: Filter;
  isActive: boolean;
  onApply: (filter: Filter) => void;
  onRename: (filter: Filter, name: string) => void;
  onRequestDelete: (filter: Filter) => void;
}

const CustomFilterTab = ({
  filter,
  isActive,
  onApply,
  onRename,
  onRequestDelete
}: CustomFilterTabProps): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();

  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging
  } = useSortable({ id: `${filter.id}` });

  const [isEditing, setIsEditing] = useState(false);
  const [isHovered, setIsHovered] = useState(false);
  const [draftName, setDraftName] = useState(filter.name);

  const style = {
    opacity: isDragging ? 0.6 : 1,
    transform: transform
      ? `translate3d(${transform.x}px, ${transform.y}px, 0)`
      : undefined,
    transition
  };

  const startEditing = (): void => {
    setDraftName(filter.name);
    setIsEditing(true);
  };

  const commitRename = (): void => {
    setIsEditing(false);
    onRename(filter, draftName);
  };

  return (
    // biome-ignore lint/a11y/noStaticElementInteractions: hover only toggles the delete button visibility
    <span
      className={cx(classes.tab, { [classes.tabActive]: isActive })}
      onMouseEnter={(): void => setIsHovered(true)}
      onMouseLeave={(): void => setIsHovered(false)}
      ref={setNodeRef}
      style={style}
    >
      {isEditing ? (
        <input
          // biome-ignore lint/a11y/noAutofocus: focus the field as soon as renaming starts
          autoFocus
          className={classes.renameInput}
          onBlur={commitRename}
          onChange={(event): void => setDraftName(event.target.value)}
          onKeyDown={(event): void => {
            if (event.key === 'Enter') {
              commitRename();
            }
            if (event.key === 'Escape') {
              setIsEditing(false);
              setDraftName(filter.name);
            }
          }}
          size={Math.max(draftName.length, 4)}
          value={draftName}
        />
      ) : (
        <>
          <button
            aria-selected={isActive}
            className={classes.label}
            data-testid={`filterView-${filter.id}`}
            onClick={(): void => onApply(filter)}
            onDoubleClick={startEditing}
            role="tab"
            type="button"
            {...attributes}
            {...listeners}
          >
            {filter.name}
          </button>
          <button
            aria-label={`${t(labelDelete)} ${filter.name}`}
            className={classes.deleteButton}
            data-testid={`deleteFilter-${filter.id}`}
            onClick={(): void => onRequestDelete(filter)}
            style={{ opacity: isHovered ? 1 : 0 }}
            type="button"
          >
            <CloseIcon fontSize="inherit" />
          </button>
        </>
      )}
    </span>
  );
};

const FilterViews = (): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const [customFilters, setCustomFilters] = useAtom(customFiltersAtom);
  const [currentFilter, setCurrentFilter] = useAtom(currentFilterAtom);
  const setAppliedFilter = useSetAtom(appliedFilterAtom);
  const applyFilter = useSetAtom(applyFilterDerivedAtom);

  const [filterToDelete, setFilterToDelete] = useState<Filter | null>(null);

  const { sendRequest: sendPatchFilter } = useRequest({ request: patchFilter });
  const { sendRequest: sendUpdateFilter } = useRequest({
    request: updateFilter
  });
  const { sendRequest: sendDeleteFilter } = useRequest({
    request: deleteFilter
  });

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor)
  );

  const customIds = customFilters.map(({ id }) => `${id}`);

  const handleDragEnd = (event: DragEndEvent): void => {
    const { active, over } = event;

    if (!over || active.id === over.id) {
      return;
    }

    const oldIndex = customIds.indexOf(active.id as string);
    const newIndex = customIds.indexOf(over.id as string);

    const reordered = arrayMove(customFilters, oldIndex, newIndex).map(
      (filter, index) => ({ ...filter, order: index })
    );

    setCustomFilters(reordered);
    sendPatchFilter({ id: active.id, order: newIndex + 1 });
  };

  const renameFilter = (filter: Filter, name: string): void => {
    const trimmed = name.trim();

    if (!trimmed || equals(trimmed, filter.name)) {
      return;
    }

    const updated = { ...filter, name: trimmed };

    sendUpdateFilter({ filter: omit(['id'], updated), id: updated.id }).then(
      () => {
        showSuccessMessage(t(labelFilterUpdated));

        if (sameId(filter)(currentFilter)) {
          setCurrentFilter(updated);
        }

        const index = findIndex(sameId(filter), customFilters);
        setCustomFilters(update(index, updated, customFilters));
      }
    );
  };

  const confirmDelete = (): void => {
    const filter = filterToDelete;
    setFilterToDelete(null);

    if (!filter) {
      return;
    }

    sendDeleteFilter(filter).then(() => {
      showSuccessMessage(t(labelFilterDeleted));

      if (sameId(filter)(currentFilter)) {
        setCurrentFilter({ ...filter, ...newFilter });
        setAppliedFilter({ ...filter, ...newFilter });
      }

      setCustomFilters(reject(sameId(filter), customFilters));
    });
  };

  return (
    <div className={classes.container} role="tablist">
      {standardFilters.map((filter) => (
        <button
          aria-selected={currentFilter.id === filter.id}
          className={cx(classes.tab, {
            [classes.tabActive]: currentFilter.id === filter.id
          })}
          data-testid={`filterView-${filter.id}`}
          key={filter.id}
          onClick={(): void => applyFilter(filter)}
          role="tab"
          type="button"
        >
          {t(filter.name)}
        </button>
      ))}
      <DndContext
        collisionDetection={closestCenter}
        onDragEnd={handleDragEnd}
        sensors={sensors}
      >
        <SortableContext
          items={customIds}
          strategy={horizontalListSortingStrategy}
        >
          {customFilters.map((filter) => (
            <CustomFilterTab
              filter={filter}
              isActive={currentFilter.id === filter.id}
              key={filter.id}
              onApply={applyFilter}
              onRename={renameFilter}
              onRequestDelete={setFilterToDelete}
            />
          ))}
        </SortableContext>
      </DndContext>

      {filterToDelete && (
        <ConfirmDialog
          labelCancel={t(labelCancel)}
          labelConfirm={t(labelDelete)}
          labelTitle={t(labelAskDelete)}
          onCancel={(): void => setFilterToDelete(null)}
          onConfirm={confirmDelete}
          open
        />
      )}
    </div>
  );
};

export default FilterViews;
