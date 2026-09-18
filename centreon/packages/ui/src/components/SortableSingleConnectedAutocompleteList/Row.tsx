import DeleteIcon from '@mui/icons-material/Delete';
import DragIndicatorIcon from '@mui/icons-material/DragIndicator';

import type { DraggableSyntheticListeners } from '@dnd-kit/core';
import type { RefObject } from 'react';

import type { SelectEntry } from '../../InputField/Select';
import type { GetEndpointParams } from '../../InputField/Select/Autocomplete/Connected';
import SingleConnectedAutocompleteField from '../../InputField/Select/Autocomplete/Connected/Single';
import { IconButton } from '../Button';
import { Tooltip } from '../Tooltip';
import type {
  SortableAutocompleteAction,
  SortableAutocompleteEntry
} from './models';
import { labelDelete, labelDragToReorder } from './translatedLabels';

interface ContentProps extends SortableAutocompleteEntry {
  attributes: Record<string, unknown>;
  index: number;
  isDragging: boolean;
  itemRef: RefObject<HTMLDivElement>;
  listeners: DraggableSyntheticListeners;
  style: React.CSSProperties;
}

interface RowFactoryProps {
  actions: Array<SortableAutocompleteAction>;
  baseEndpoint?: string;
  classes: Record<string, string>;
  field: string;
  getEndpoint: (params: GetEndpointParams) => string;
  getOptionDisabled?: (option: SelectEntry) => boolean;
  initialPage?: number;
  onDelete: (id: string) => void;
  onValueChange: (id: string, value: SelectEntry | null) => void;
  placeholder?: string;
  selectorLabel: string;
  t: (key: string) => string;
}

export const Row = ({
  actions,
  baseEndpoint,
  classes,
  field,
  getEndpoint,
  getOptionDisabled,
  initialPage,
  onDelete,
  onValueChange,
  placeholder,
  selectorLabel,
  t
}: RowFactoryProps): ((props: ContentProps) => JSX.Element) => {
  const Content = ({
    id,
    value,
    index,
    attributes,
    listeners,
    style,
    itemRef
  }: ContentProps): JSX.Element => {
    const entry: SortableAutocompleteEntry = { id, value };
    const getActionSx = (
      action: SortableAutocompleteAction
    ): { color: string } | undefined =>
      action.color ? { color: action.color } : undefined;

    const changeValue = (
      _event: React.SyntheticEvent,
      newValue: unknown
    ): void => onValueChange(id, newValue as SelectEntry | null);

    return (
      <div className={classes.row} ref={itemRef} style={style}>
        <div className={classes.selector}>
          <SingleConnectedAutocompleteField
            baseEndpoint={baseEndpoint}
            field={field}
            fullWidth
            getEndpoint={getEndpoint}
            getOptionDisabled={getOptionDisabled}
            initialPage={initialPage}
            label={selectorLabel}
            onChange={changeValue}
            placeholder={placeholder}
            value={value}
          />
        </div>
        <div className={classes.actions}>
          {actions.map((action) => (
            <Tooltip key={action.id} label={t(action.label)}>
              <IconButton
                aria-label={t(action.label)}
                data-testid={action.id}
                disabled={action.isDisabled?.(entry, index)}
                icon={action.icon}
                onClick={(): void => action.onClick(entry, index)}
                sx={getActionSx(action)}
              />
            </Tooltip>
          ))}
          <Tooltip label={t(labelDelete)}>
            <IconButton
              aria-label={t(labelDelete)}
              data-testid="delete-row"
              icon={<DeleteIcon fontSize="small" />}
              onClick={(): void => onDelete(id)}
              sx={{ color: 'error.main' }}
            />
          </Tooltip>
          <Tooltip label={t(labelDragToReorder)}>
            <IconButton
              aria-label={t(labelDragToReorder)}
              className={classes.dragHandle}
              data-testid="drag-handle"
              icon={<DragIndicatorIcon fontSize="small" />}
              {...listeners}
              {...attributes}
            />
          </Tooltip>
        </div>
      </div>
    );
  };

  return Content;
};
