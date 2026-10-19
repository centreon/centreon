import { useState } from 'react';

import { ItemComposition } from '.';
import type { Props as CompositionProps } from './ItemComposition';

interface Props
  extends Partial<
    Omit<CompositionProps, 'children' | 'labelAdd' | 'onAddItem'>
  > {
  deleteButtonHidden?: boolean;
}

// Records the calls of the callbacks in `calls`.
export const Default = ({
  deleteButtonHidden,
  ...props
}: Props): JSX.Element => {
  const [calls, setCalls] = useState<Array<string>>([]);
  const record = (call: string) => (): void =>
    setCalls((current) => [...current, call]);

  return (
    <>
      <ItemComposition {...props} labelAdd="Add" onAddItem={record('add')}>
        {['Item 1', 'Item 2'].map((item) => (
          <ItemComposition.Item
            deleteButtonHidden={deleteButtonHidden}
            key={item}
            labelDelete="Delete"
            onDeleteItem={record(`delete ${item}`)}
          >
            <div>{item}</div>
          </ItemComposition.Item>
        ))}
      </ItemComposition>
      <output data-testid="calls">{calls.join(', ')}</output>
    </>
  );
};
