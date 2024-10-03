import { ForwardedRef, forwardRef } from 'react';

interface Props extends Record<string, unknown> {
  Content: (props) => JSX.Element;
  isInDragOverlay?: boolean;
}

const Item = forwardRef(
  ({ Content, ...props }: Props, ref: ForwardedRef<HTMLDivElement>) => {
    return <Content {...props} itemRef={ref} />;
  }
);

export default Item;
