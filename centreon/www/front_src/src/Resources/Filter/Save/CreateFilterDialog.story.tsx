import { useState } from 'react';

import CreateFilterDialog from './CreateFilterDialog';

interface Props {
  name?: string;
  /** Makes the save request fail. */
  reject?: boolean;
}

// Counts the cancel and success callbacks of the Cypress stubs.
export const Default = ({ name, reject = false }: Props): JSX.Element => {
  const [cancelCount, setCancelCount] = useState(0);
  const [successCount, setSuccessCount] = useState(0);

  return (
    <>
      <CreateFilterDialog
        callbackSuccess={() => setSuccessCount((count) => count + 1)}
        onCancel={() => setCancelCount((count) => count + 1)}
        open
        payloadAction={{ filter: { name } } as never}
        request={
          (() => () => (reject ? Promise.reject() : Promise.resolve())) as never
        }
      />
      <form hidden>
        <input
          data-testid="cancel-count"
          readOnly
          value={String(cancelCount)}
        />
        <input
          data-testid="success-count"
          readOnly
          value={String(successCount)}
        />
      </form>
    </>
  );
};
