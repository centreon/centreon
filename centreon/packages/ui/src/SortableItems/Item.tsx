import { forwardRef } from 'react';

interface Props extends Record<string, unknown> {
  Content: (props: Record<string, unknown>) => JSX.Element;
  isInDragOverlay?: boolean;
}

const Item = forwardRef<HTMLDivElement, Props>(({ Content, ...props }, ref) => {
  const ContentComponent = Content as React.ComponentType<
    Record<string, unknown>
  >;
  return <ContentComponent {...props} itemRef={ref} />;
});

export default Item;
