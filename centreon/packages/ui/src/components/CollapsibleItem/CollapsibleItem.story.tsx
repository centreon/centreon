import { CollapsibleItem } from './CollapsibleItem';

interface Props {
  compact?: boolean;
  customTitle?: boolean;
  defaultExpanded?: boolean;
}

export const Default = ({
  compact,
  customTitle,
  defaultExpanded
}: Props): JSX.Element => (
  <CollapsibleItem
    compact={compact}
    defaultExpanded={defaultExpanded}
    // Its type admits elements only as strings, yet it renders them.
    title={(customTitle ? <div>Customized title</div> : 'Title') as never}
  >
    Content
  </CollapsibleItem>
);
