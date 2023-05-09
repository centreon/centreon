import { List as ListRoot } from './List';
import { ListItem } from './Item';

export const List = Object.assign(ListRoot, {
  Item: ListItem
});