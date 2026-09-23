import DeleteIcon from '@mui/icons-material/Delete';
import DragIndicatorIcon from '@mui/icons-material/DragIndicator';

import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { ComponentPropsWithRef, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { IconButton } from '../Button';
import { Tooltip } from '../Tooltip';
import type { RenderRowParams, RowParams, SortableRowAction } from './models';
import { labelDelete, labelDragToReorder } from './translatedLabels';

type RowButtonProps = ComponentPropsWithRef<typeof IconButton> & {
  label: string;
};

// 30px buttons at a 32px pitch. The icon size is set here because the Tooltip
// overrides IconButton's own small-size class, and consumer icons may be any size.
const RowButton = ({
  className = '',
  label,
  ...buttonProps
}: RowButtonProps): JSX.Element => (
  <Tooltip label={label}>
    <IconButton
      {...buttonProps}
      aria-label={label}
      className={`[&_.MuiSvgIcon-root]:text-xl ${className}`}
      size="small"
      variant="ghost"
    />
  </Tooltip>
);

interface Props<T> {
  actions: Array<SortableRowAction>;
  className?: string;
  draggable: boolean;
  id: string;
  onDelete: (id: string) => void;
  onUpdate: (id: string, update: (value: T) => T) => void;
  renderRow: (params: RenderRowParams<T>) => ReactNode;
  rowParams: RowParams<T>;
}

export const Row = <T,>({
  actions,
  className = '',
  draggable,
  id,
  onDelete,
  onUpdate,
  renderRow,
  rowParams
}: Props<T>): JSX.Element => {
  const { t } = useTranslation();
  const {
    attributes,
    isDragging,
    listeners,
    setActivatorNodeRef,
    setNodeRef,
    transform,
    transition
  } = useSortable({ disabled: !draggable, id });

  const setValue = (value: T): void => onUpdate(id, () => value);
  const setField = <K extends keyof T>(field: K, fieldValue: T[K]): void =>
    onUpdate(
      id,
      (value) => ({ ...(value as object), [field]: fieldValue }) as T
    );

  return (
    <li
      className={`relative flex w-full items-center gap-2 ${isDragging ? 'z-1 opacity-70' : ''} ${className}`}
      data-entry-id={id}
      ref={setNodeRef}
      style={{ transform: CSS.Translate.toString(transform), transition }}
    >
      <div className="min-w-0 flex-1" data-row-fields>
        {renderRow({ ...rowParams, setField, setValue })}
      </div>
      <div className="flex flex-none items-center gap-0.5">
        {actions.map(
          ({ className, icon, id: actionId, isDisabled, label, onClick }) => (
            <RowButton
              className={className}
              data-testid={actionId}
              disabled={isDisabled}
              icon={icon}
              key={actionId}
              label={t(label)}
              onClick={onClick}
            />
          )
        )}
        <RowButton
          className="text-error-main"
          data-testid="delete-row"
          icon={<DeleteIcon />}
          label={t(labelDelete)}
          onClick={(): void => onDelete(id)}
        />
        {draggable && (
          <RowButton
            {...attributes}
            {...listeners}
            className="cursor-grab touch-none active:cursor-grabbing"
            data-testid="drag-handle"
            icon={<DragIndicatorIcon />}
            label={t(labelDragToReorder)}
            ref={setActivatorNodeRef}
          />
        )}
      </div>
    </li>
  );
};
