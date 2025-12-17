import { Avatar as AvatarRoot } from './Avatar';
import { AvatarSkeleton } from './AvatarSkeleton';
import { ListItem as ListItemRoot } from './ListItem';
import { Text as TextRoot } from './Text';
import { TextSkeleton } from './TextSkeleton';

export const ListItem = Object.assign(ListItemRoot, {
  Avatar: Object.assign(AvatarRoot, {
    Skeleton: AvatarSkeleton
  }),
  Text: Object.assign(TextRoot, {
    Skeleton: TextSkeleton
  })
});
