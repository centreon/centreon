import { List as ListRoot } from './List';
import { Item } from './Item';
import { ItemText } from './ItemText';
import { Avatar } from './Avatar';

export const List = Object.assign(ListRoot, {
  Avatar,
  Item,
  ItemText
});
