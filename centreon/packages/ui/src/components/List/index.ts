import { List as ListRoot } from './List';
import { ListItem } from './Item';
import { ListEmptyState } from './EmptyState';

export const List = Object.assign(ListRoot, {
  Item: ListItem,
  EmptyState: ListEmptyState
});