import type { ListingVariant } from '@centreon/ui-context';

export interface StylesProps {
  isDragging: boolean;
  isInDragOverlay?: boolean;
  listingVariant?: ListingVariant;
}
