import * as React from 'react';

interface Props extends Record<string, unknown> {
  Content: (props) => JSX.Element;
  isInDragOverlay?: boolean;
}

const Item = React.forwardRef(
  ({ Content, ...props }: Props, ref: React.ForwardedRef<HTMLDivElement>) => {
    const getCellHeaderHovered = (e) => console.log(e);

    return (
      <Content
        {...props}
        getCellHeaderHovered={getCellHeaderHovered}
        itemRef={ref}
      />
    );
  }
);

export default Item;
