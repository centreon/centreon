import * as React from 'react';

interface Props extends Record<string, unknown> {
  Content: (props) => JSX.Element;
  isInDragOverlay?: boolean;
}

const Item = React.forwardRef(
  ({ Content, ...props }: Props, ref: React.ForwardedRef<HTMLDivElement>) => {
    return <Content {...props} itemRef={ref} />;
  }
);

export default Item;
