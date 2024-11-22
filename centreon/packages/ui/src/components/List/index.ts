import { ListItem } from './Item';
import { List as ListRoot } from './List';

export const List = Object.assign(ListRoot, {
  Item: ListItem
});
