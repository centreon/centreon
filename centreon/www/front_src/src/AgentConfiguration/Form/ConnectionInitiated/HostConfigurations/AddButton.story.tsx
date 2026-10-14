import { useState } from 'react';

import AddButton from './AddButton';

interface Props {
  addButtonDisabled?: boolean;
}

// The click count stands for the onAddItem stub of the Cypress spec.
export const Default = ({ addButtonDisabled = false }: Props): JSX.Element => {
  const [clicks, setClicks] = useState(0);

  return (
    <>
      <AddButton
        addButtonDisabled={addButtonDisabled}
        onAddItem={() => setClicks((count) => count + 1)}
      />
      <form hidden>
        <input data-testid="add-count" readOnly value={String(clicks)} />
      </form>
    </>
  );
};
